<?php

namespace App\Http\Controllers\Finance;

use App\Enums\JournalSource;
use App\Enums\JournalStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\SaveJournalRequest;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Student;
use App\Services\Finance\JournalPoster;
use App\Services\Finance\PostableEntry;
use App\Services\Finance\PostableLine;
use App\Services\SchoolSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManualJournalController extends Controller
{
    /**
     * Display a listing of manual journal entries.
     */
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $search = $request->string('search')->toString();
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        $journals = JournalEntry::query()
            ->with(['author:id,username', 'lines:id,journal_entry_id,debit,credit'])
            ->withCount('lines')
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('number', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($from !== '', fn ($q) => $q->where('entry_date', '>=', $from))
            ->when($to !== '', fn ($q) => $q->where('entry_date', '<=', $to))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (JournalEntry $journal) => [
                'id' => $journal->id,
                'number' => $journal->number,
                'entry_date' => $journal->entry_date->toDateString(),
                'description' => $journal->description,
                'status' => $journal->status->value,
                'status_label' => $journal->status->label(),
                'source_type' => $journal->source_type->value,
                'source_type_label' => $journal->source_type->label(),
                'reverses_entry_id' => $journal->reverses_entry_id,
                'total_debit' => $journal->totalDebit(),
                'total_credit' => $journal->totalCredit(),
                'lines_count' => $journal->lines_count,
                'has_attachment' => $journal->attachment_path !== null,
                'author' => $journal->author?->username,
                'posted_at' => $journal->posted_at?->toIso8601String(),
            ]);

        return Inertia::render('finance/journals/index', [
            'journals' => $journals,
            'filters' => [
                'status' => $status,
                'search' => $search,
                'from' => $from,
                'to' => $to,
            ],
            'canManage' => $request->user()->hasAnyRole(UserRole::Admin, UserRole::Finance),
        ]);
    }

    /**
     * Show the form for creating a new manual journal entry.
     */
    public function create(): Response
    {
        $accounts = Account::query()
            ->where('is_active', true)
            ->where('is_postable', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'is_contra', 'is_cash'])
            ->map(fn (Account $acc) => [
                'id' => $acc->id,
                'code' => $acc->code,
                'name' => $acc->name,
                'type' => $acc->type->value,
                'normal_balance' => $acc->normalBalance(),
                'label' => "{$acc->code} - {$acc->name}",
            ]);

        $students = Student::query()
            ->orderBy('full_name')
            ->limit(100)
            ->get(['id', 'full_name', 'student_number'])
            ->map(fn (Student $s) => [
                'id' => $s->id,
                'name' => $s->full_name,
                'nis' => $s->student_number,
                'label' => "{$s->student_number} - {$s->full_name}",
            ]);

        return Inertia::render('finance/journals/form', [
            'journal' => null,
            'accounts' => $accounts,
            'students' => $students,
        ]);
    }

    /**
     * Store a newly created manual journal entry (as draft or directly posted).
     */
    public function store(SaveJournalRequest $request, JournalPoster $poster): RedirectResponse
    {
        $action = $request->input('action', 'post');
        $file = $request->file('attachment');
        $uploaded = $file?->store('journals', 'local');
        $attachmentPath = is_string($uploaded) ? $uploaded : null;

        if ($action === 'draft') {
            $journal = DB::transaction(function () use ($request, $attachmentPath) {
                $entry = JournalEntry::create([
                    'number' => null,
                    'entry_date' => $request->input('entry_date'),
                    'description' => $request->input('description'),
                    'status' => JournalStatus::Draft,
                    'source_type' => JournalSource::Manual,
                    'attachment_path' => $attachmentPath,
                    'created_by_user_id' => auth()->id() !== null ? (int) auth()->id() : null,
                    'posted_at' => null,
                ]);

                foreach ($request->input('lines') as $line) {
                    JournalLine::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $line['account_id'],
                        'debit' => $line['debit'],
                        'credit' => $line['credit'],
                        'student_id' => $line['student_id'] ?? null,
                        'memo' => $line['memo'] ?? null,
                    ]);
                }

                return $entry;
            });

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Draf jurnal berhasil disimpan.',
            ]);

            return to_route('finance.journals.show', $journal);
        }

        // Post action
        $lines = array_values(array_map(
            fn (array $l) => new PostableLine(
                accountId: (int) $l['account_id'],
                debit: $l['debit'],
                credit: $l['credit'],
                studentId: ! empty($l['student_id']) ? (int) $l['student_id'] : null,
                memo: $l['memo'] ?? null,
            ),
            $request->input('lines')
        ));

        $entry = new PostableEntry(
            entryDate: $request->input('entry_date'),
            description: $request->input('description'),
            sourceType: JournalSource::Manual,
            lines: $lines,
            attachmentPath: $attachmentPath,
            createdByUserId: auth()->id() !== null ? (int) auth()->id() : null,
        );

        $journal = DB::transaction(fn () => $poster->post($entry));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Jurnal berhasil diposting.',
        ]);

        return to_route('finance.journals.show', $journal);
    }

    /**
     * Display the specified journal entry.
     */
    public function show(Request $request, JournalEntry $journal): Response
    {
        $journal->load([
            'lines.account',
            'lines.student',
            'author:id,username',
            'reversesEntry:id,number,entry_date,description',
            'reversalEntries:id,number,entry_date,description',
        ]);

        return Inertia::render('finance/journals/show', [
            'journal' => [
                'id' => $journal->id,
                'number' => $journal->number,
                'entry_date' => $journal->entry_date->toDateString(),
                'description' => $journal->description,
                'status' => $journal->status->value,
                'status_label' => $journal->status->label(),
                'source_type' => $journal->source_type->value,
                'source_type_label' => $journal->source_type->label(),
                'source_id' => $journal->source_id,
                'reverses_entry' => $journal->reversesEntry ? [
                    'id' => $journal->reversesEntry->id,
                    'number' => $journal->reversesEntry->number,
                    'entry_date' => $journal->reversesEntry->entry_date->toDateString(),
                    'description' => $journal->reversesEntry->description,
                ] : null,
                'reversal_entries' => $journal->reversalEntries->map(fn ($r) => [
                    'id' => $r->id,
                    'number' => $r->number,
                    'entry_date' => $r->entry_date->toDateString(),
                    'description' => $r->description,
                ]),
                'has_attachment' => $journal->attachment_path !== null,
                'author' => $journal->author?->username,
                'posted_at' => $journal->posted_at?->toIso8601String(),
                'total_debit' => $journal->totalDebit(),
                'total_credit' => $journal->totalCredit(),
                'is_balanced' => $journal->isBalanced(),
                'lines' => $journal->lines->map(fn (JournalLine $line) => [
                    'id' => $line->id,
                    'account_id' => $line->account_id,
                    'account_code' => $line->account->code,
                    'account_name' => $line->account->name,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                    'student_id' => $line->student_id,
                    'student_name' => $line->student?->full_name,
                    'memo' => $line->memo,
                ]),
            ],
            'overpayment_id' => $journal->source_type->isOverpayment() ? $journal->source_id : null,
            'canReverse' => ! $this->isOverpaymentJournal($journal),
            'canManage' => $request->user()->hasAnyRole(UserRole::Admin, UserRole::Finance),
        ]);
    }

    /**
     * Show form to edit a draft journal entry.
     */
    public function edit(JournalEntry $journal): Response
    {
        if ($journal->isPosted()) {
            abort(422, 'Jurnal yang telah diposting tidak dapat diedit.');
        }

        $journal->load(['lines']);

        $accounts = Account::query()
            ->where('is_active', true)
            ->where('is_postable', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'is_contra', 'is_cash'])
            ->map(fn (Account $acc) => [
                'id' => $acc->id,
                'code' => $acc->code,
                'name' => $acc->name,
                'type' => $acc->type->value,
                'normal_balance' => $acc->normalBalance(),
                'label' => "{$acc->code} - {$acc->name}",
            ]);

        $students = Student::query()
            ->orderBy('full_name')
            ->limit(100)
            ->get(['id', 'full_name', 'student_number'])
            ->map(fn (Student $s) => [
                'id' => $s->id,
                'name' => $s->full_name,
                'nis' => $s->student_number,
                'label' => "{$s->student_number} - {$s->full_name}",
            ]);

        return Inertia::render('finance/journals/form', [
            'journal' => [
                'id' => $journal->id,
                'entry_date' => $journal->entry_date->toDateString(),
                'description' => $journal->description,
                'has_attachment' => $journal->attachment_path !== null,
                'lines' => $journal->lines->map(fn (JournalLine $l) => [
                    'account_id' => $l->account_id,
                    'debit' => $l->debit,
                    'credit' => $l->credit,
                    'student_id' => $l->student_id,
                    'memo' => $l->memo,
                ]),
            ],
            'accounts' => $accounts,
            'students' => $students,
        ]);
    }

    /**
     * Update an existing draft journal entry or post it.
     */
    public function update(SaveJournalRequest $request, JournalEntry $journal, JournalPoster $poster): RedirectResponse
    {
        if ($journal->isPosted()) {
            abort(422, 'Jurnal yang telah diposting tidak dapat diedit.');
        }

        $action = $request->input('action', 'post');

        if ($request->hasFile('attachment')) {
            if ($journal->attachment_path) {
                Storage::disk('local')->delete($journal->attachment_path);
            }
            $uploaded = $request->file('attachment')?->store('journals', 'local');
            $attachmentPath = is_string($uploaded) ? $uploaded : null;
        } else {
            $attachmentPath = $journal->attachment_path;
        }

        if ($action === 'draft') {
            DB::transaction(function () use ($request, $journal, $attachmentPath) {
                $journal->update([
                    'entry_date' => $request->input('entry_date'),
                    'description' => $request->input('description'),
                    'attachment_path' => $attachmentPath,
                ]);

                $journal->lines()->delete();

                foreach ($request->input('lines') as $line) {
                    JournalLine::create([
                        'journal_entry_id' => $journal->id,
                        'account_id' => $line['account_id'],
                        'debit' => $line['debit'],
                        'credit' => $line['credit'],
                        'student_id' => $line['student_id'] ?? null,
                        'memo' => $line['memo'] ?? null,
                    ]);
                }
            });

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Draf jurnal berhasil diperbarui.',
            ]);

            return to_route('finance.journals.show', $journal);
        }

        // Post action
        $lines = array_values(array_map(
            fn (array $l) => new PostableLine(
                accountId: (int) $l['account_id'],
                debit: $l['debit'],
                credit: $l['credit'],
                studentId: ! empty($l['student_id']) ? (int) $l['student_id'] : null,
                memo: $l['memo'] ?? null,
            ),
            $request->input('lines')
        ));

        $postableEntry = new PostableEntry(
            entryDate: $request->input('entry_date'),
            description: $request->input('description'),
            sourceType: JournalSource::Manual,
            lines: $lines,
            attachmentPath: $attachmentPath,
            createdByUserId: auth()->id() !== null ? (int) auth()->id() : null,
        );

        $posted = DB::transaction(function () use ($journal, $poster, $postableEntry) {
            $journal->lines()->delete();
            $journal->delete();

            return $poster->post($postableEntry);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Jurnal berhasil diposting.',
        ]);

        return to_route('finance.journals.show', $posted);
    }

    /**
     * Delete a draft journal entry.
     */
    public function destroy(JournalEntry $journal): RedirectResponse
    {
        if ($journal->isPosted()) {
            abort(422, 'Jurnal yang telah diposting tidak dapat dihapus.');
        }

        if ($journal->attachment_path) {
            Storage::disk('local')->delete($journal->attachment_path);
        }

        $journal->lines()->delete();
        $journal->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Draf jurnal berhasil dihapus.',
        ]);

        return to_route('finance.journals.index');
    }

    /**
     * Post a draft journal entry.
     */
    public function post(JournalEntry $journal, JournalPoster $poster): RedirectResponse
    {
        if ($journal->isPosted()) {
            abort(422, 'Jurnal ini sudah diposting sebelumnya.');
        }

        $journal->loadMissing('lines');

        $lines = array_values($journal->lines->map(
            fn (JournalLine $l) => new PostableLine(
                accountId: $l->account_id,
                debit: $l->debit,
                credit: $l->credit,
                studentId: $l->student_id,
                billId: $l->bill_id,
                memo: $l->memo,
            )
        )->all());

        $postableEntry = new PostableEntry(
            entryDate: $journal->entry_date,
            description: $journal->description,
            sourceType: JournalSource::Manual,
            lines: $lines,
            attachmentPath: $journal->attachment_path,
            createdByUserId: auth()->id() !== null ? (int) auth()->id() : null,
        );

        $posted = DB::transaction(function () use ($journal, $poster, $postableEntry) {
            $journal->lines()->delete();
            $journal->delete();

            return $poster->post($postableEntry);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Jurnal berhasil diposting.',
        ]);

        return to_route('finance.journals.show', $posted);
    }

    /**
     * Reverse a posted journal entry.
     */
    public function reverse(Request $request, JournalEntry $journal, JournalPoster $poster, SchoolSettings $settings): RedirectResponse
    {
        abort_if($this->isOverpaymentJournal($journal), 422, 'Gunakan pembatalan pada detail kelebihan pembayaran; jurnal otomatis tidak dapat dibalik langsung.');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
        ]);

        if (! $journal->isPosted()) {
            abort(422, 'Hanya jurnal yang telah diposting yang dapat dibalik.');
        }

        $targetDate = ! empty($validated['date'])
            ? Carbon::parse($validated['date'])
            : Carbon::parse($settings->todayDate());

        $reversal = DB::transaction(fn () => $poster->reverse(
            original: $journal,
            on: $targetDate,
            reason: $validated['reason'],
        ));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Jurnal pembalik {$reversal->number} berhasil dibuat.",
        ]);

        return to_route('finance.journals.show', $reversal);
    }

    private function isOverpaymentJournal(JournalEntry $journal): bool
    {
        $seen = [];
        while ($journal !== null && ! isset($seen[$journal->id])) {
            if ($journal->source_type->isOverpayment()) {
                return true;
            }
            $seen[$journal->id] = true;
            $journal = $journal->reversesEntry;
        }

        return false;
    }

    /**
     * Download or view receipt attachment.
     */
    public function attachment(JournalEntry $journal): StreamedResponse
    {
        if (! $journal->attachment_path || ! Storage::disk('local')->exists($journal->attachment_path)) {
            abort(404, 'Lampiran nota/kuitansi tidak ditemukan.');
        }

        return Storage::disk('local')->download($journal->attachment_path);
    }
}

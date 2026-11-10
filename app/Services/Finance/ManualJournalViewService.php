<?php

namespace App\Services\Finance;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Student;
use Illuminate\Pagination\LengthAwarePaginator;

class ManualJournalViewService
{
    /**
     * Build the paginated listing of manual journal entries.
     *
     * @param  array{status?: string, search?: string, from?: string, to?: string}  $filters
     * @return LengthAwarePaginator<int, array{
     *     id: int,
     *     number: string|null,
     *     entry_date: string,
     *     description: string,
     *     status: string,
     *     status_label: string,
     *     source_type: string,
     *     source_type_label: string,
     *     reverses_entry_id: int|null,
     *     total_debit: string,
     *     total_credit: string,
     *     lines_count: int,
     *     has_attachment: bool,
     *     author: string|null,
     *     posted_at: string|null
     * }>
     */
    public function getPaginatedJournals(array $filters): LengthAwarePaginator
    {
        $status = $filters['status'] ?? '';
        $search = $filters['search'] ?? '';
        $from = $filters['from'] ?? '';
        $to = $filters['to'] ?? '';

        /** @var LengthAwarePaginator<int, JournalEntry> $paginator */
        $paginator = JournalEntry::query()
            ->with(['author:id,username', 'lines:id,journal_entry_id,debit,credit'])
            ->withCount('lines')
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->whereLike('number', "%{$search}%")
                        ->orWhereLike('description', "%{$search}%");
                });
            })
            ->when($from !== '', fn ($q) => $q->where('entry_date', '>=', $from))
            ->when($to !== '', fn ($q) => $q->where('entry_date', '<=', $to))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return $paginator->through(fn (JournalEntry $journal): array => [
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
    }

    /**
     * Get active postable accounts formatted for the journal entry form.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAccountOptions(): array
    {
        return Account::query()
            ->where('is_active', true)
            ->where('is_postable', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'is_contra', 'is_cash'])
            ->map(fn (Account $acc): array => [
                'id' => $acc->id,
                'code' => $acc->code,
                'name' => $acc->name,
                'type' => $acc->type->value,
                'normal_balance' => $acc->normalBalance(),
                'label' => "{$acc->code} - {$acc->name}",
            ])
            ->all();
    }

    /**
     * Get student options formatted for the journal entry form.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getStudentOptions(): array
    {
        return Student::query()
            ->orderBy('full_name')
            ->limit(100)
            ->get(['id', 'full_name', 'student_number'])
            ->map(fn (Student $s): array => [
                'id' => $s->id,
                'name' => $s->full_name,
                'nis' => $s->student_number,
                'label' => "{$s->student_number} - {$s->full_name}",
            ])
            ->all();
    }

    /**
     * Format a journal entry model into a detailed array for the show view.
     *
     * @return array<string, mixed>
     */
    public function formatJournalDetail(JournalEntry $journal): array
    {
        $journal->load([
            'lines.account',
            'lines.student',
            'author:id,username',
            'reversesEntry:id,number,entry_date,description',
            'reversalEntries:id,number,entry_date,description',
        ]);

        return [
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
            'reversal_entries' => $journal->reversalEntries->map(fn ($r): array => [
                'id' => $r->id,
                'number' => $r->number,
                'entry_date' => $r->entry_date->toDateString(),
                'description' => $r->description,
            ])->all(),
            'has_attachment' => $journal->attachment_path !== null,
            'author' => $journal->author?->username,
            'posted_at' => $journal->posted_at?->toIso8601String(),
            'total_debit' => $journal->totalDebit(),
            'total_credit' => $journal->totalCredit(),
            'is_balanced' => $journal->isBalanced(),
            'lines' => $journal->lines->map(fn (JournalLine $line): array => [
                'id' => $line->id,
                'account_id' => $line->account_id,
                'account_code' => $line->account->code,
                'account_name' => $line->account->name,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'student_id' => $line->student_id,
                'student_name' => $line->student?->full_name,
                'memo' => $line->memo,
            ])->all(),
        ];
    }

    /**
     * Format a draft journal entry into form data for the edit view.
     *
     * @return array<string, mixed>
     */
    public function formatJournalForEdit(JournalEntry $journal): array
    {
        $journal->loadMissing(['lines']);

        return [
            'id' => $journal->id,
            'entry_date' => $journal->entry_date->toDateString(),
            'description' => $journal->description,
            'has_attachment' => $journal->attachment_path !== null,
            'lines' => $journal->lines->map(fn (JournalLine $l): array => [
                'account_id' => $l->account_id,
                'debit' => $l->debit,
                'credit' => $l->credit,
                'student_id' => $l->student_id,
                'memo' => $l->memo,
            ])->all(),
        ];
    }

    /**
     * Check whether a journal entry is connected to an overpayment.
     */
    public function isOverpaymentJournal(JournalEntry $journal): bool
    {
        $seen = [];
        $current = $journal;
        while ($current !== null && ! isset($seen[$current->id])) {
            if ($current->source_type->isOverpayment()) {
                return true;
            }
            $seen[$current->id] = true;
            $current = $current->reversesEntry;
        }

        return false;
    }
}

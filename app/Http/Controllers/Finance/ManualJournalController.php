<?php

namespace App\Http\Controllers\Finance;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\SaveJournalRequest;
use App\Models\JournalEntry;
use App\Services\Finance\ManualJournalService;
use App\Services\Finance\ManualJournalViewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManualJournalController extends Controller
{
    public function __construct(
        private readonly ManualJournalService $journalService,
        private readonly ManualJournalViewService $viewService,
    ) {}

    /**
     * Display a listing of manual journal entries.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'status' => $request->string('status')->toString(),
            'search' => $request->string('search')->toString(),
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
        ];

        return Inertia::render('finance/journals/index', [
            'journals' => $this->viewService->getPaginatedJournals($filters),
            'filters' => $filters,
            'canManage' => $request->user()->hasAnyRole(UserRole::Admin, UserRole::Finance),
        ]);
    }

    /**
     * Show the form for creating a new manual journal entry.
     */
    public function create(): Response
    {
        return Inertia::render('finance/journals/form', [
            'journal' => null,
            'accounts' => $this->viewService->getAccountOptions(),
            'students' => $this->viewService->getStudentOptions(),
        ]);
    }

    /**
     * Store a newly created manual journal entry (as draft or directly posted).
     */
    public function store(SaveJournalRequest $request): RedirectResponse
    {
        $action = $request->input('action', 'post');
        $userId = auth()->id() !== null ? (int) auth()->id() : null;

        if ($action === 'draft') {
            $journal = $this->journalService->createDraft(
                data: $request->validated(),
                attachment: $request->file('attachment'),
                userId: $userId,
            );

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('toasts.finance.journal_draft_saved'),
            ]);

            return to_route('finance.journals.show', $journal);
        }

        $journal = $this->journalService->createAndPost(
            data: $request->validated(),
            attachment: $request->file('attachment'),
            userId: $userId,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('toasts.finance.journal_posted'),
        ]);

        return to_route('finance.journals.show', $journal);
    }

    /**
     * Display the specified journal entry.
     */
    public function show(Request $request, JournalEntry $journal): Response
    {
        return Inertia::render('finance/journals/show', [
            'journal' => $this->viewService->formatJournalDetail($journal),
            'overpayment_id' => $journal->source_type->isOverpayment() ? $journal->source_id : null,
            'canReverse' => ! $this->viewService->isOverpaymentJournal($journal),
            'canManage' => $request->user()->hasAnyRole(UserRole::Admin, UserRole::Finance),
        ]);
    }

    /**
     * Show form to edit a draft journal entry.
     */
    public function edit(JournalEntry $journal): Response
    {
        if ($journal->isPosted()) {
            abort(422, __('toasts.finance.journal_posted_no_edit'));
        }

        return Inertia::render('finance/journals/form', [
            'journal' => $this->viewService->formatJournalForEdit($journal),
            'accounts' => $this->viewService->getAccountOptions(),
            'students' => $this->viewService->getStudentOptions(),
        ]);
    }

    /**
     * Update an existing draft journal entry or post it.
     */
    public function update(SaveJournalRequest $request, JournalEntry $journal): RedirectResponse
    {
        if ($journal->isPosted()) {
            abort(422, __('toasts.finance.journal_posted_no_edit'));
        }

        $action = $request->input('action', 'post');
        $hasAttachment = $request->hasFile('attachment');
        $attachment = $request->file('attachment');
        $userId = auth()->id() !== null ? (int) auth()->id() : null;

        if ($action === 'draft') {
            $this->journalService->updateDraft(
                journal: $journal,
                data: $request->validated(),
                attachment: $attachment,
                hasAttachmentFile: $hasAttachment,
            );

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('toasts.finance.journal_draft_updated'),
            ]);

            return to_route('finance.journals.show', $journal);
        }

        $posted = $this->journalService->updateAndPost(
            journal: $journal,
            data: $request->validated(),
            attachment: $attachment,
            hasAttachmentFile: $hasAttachment,
            userId: $userId,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('toasts.finance.journal_posted'),
        ]);

        return to_route('finance.journals.show', $posted);
    }

    /**
     * Delete a draft journal entry.
     */
    public function destroy(JournalEntry $journal): RedirectResponse
    {
        if ($journal->isPosted()) {
            abort(422, __('toasts.finance.journal_posted_no_delete'));
        }

        $this->journalService->deleteDraft($journal);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('toasts.finance.journal_draft_deleted'),
        ]);

        return to_route('finance.journals.index');
    }

    /**
     * Post a draft journal entry.
     */
    public function post(JournalEntry $journal): RedirectResponse
    {
        if ($journal->isPosted()) {
            abort(422, __('toasts.finance.journal_already_posted'));
        }

        $userId = auth()->id() !== null ? (int) auth()->id() : null;
        $posted = $this->journalService->postDraft($journal, $userId);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('toasts.finance.journal_posted'),
        ]);

        return to_route('finance.journals.show', $posted);
    }

    /**
     * Reverse a posted journal entry.
     */
    public function reverse(Request $request, JournalEntry $journal): RedirectResponse
    {
        abort_if($this->viewService->isOverpaymentJournal($journal), 422, __('toasts.finance.journal_overpayment_reverse'));

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
        ]);

        if (! $journal->isPosted()) {
            abort(422, __('toasts.finance.journal_reverse_needs_posted'));
        }

        $reversal = $this->journalService->reverse(
            journal: $journal,
            reason: $validated['reason'],
            date: $validated['date'] ?? null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('toasts.finance.journal_reversed', ['number' => $reversal->number]),
        ]);

        return to_route('finance.journals.show', $reversal);
    }

    /**
     * Download or view receipt attachment.
     */
    public function attachment(JournalEntry $journal): StreamedResponse
    {
        if (! $journal->attachment_path || ! Storage::disk('local')->exists($journal->attachment_path)) {
            abort(404, __('toasts.finance.attachment_not_found'));
        }

        return Storage::disk('local')->download($journal->attachment_path);
    }
}

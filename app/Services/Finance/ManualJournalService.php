<?php

namespace App\Services\Finance;

use App\Enums\JournalSource;
use App\Enums\JournalStatus;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Services\SchoolSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ManualJournalService
{
    public function __construct(
        private readonly JournalPoster $poster,
        private readonly SchoolSettings $settings,
    ) {}

    /**
     * Save a manual journal entry as a draft.
     *
     * @param  array<string, mixed>  $data
     */
    public function createDraft(array $data, ?UploadedFile $attachment = null, ?int $userId = null): JournalEntry
    {
        $attachmentPath = $attachment?->store('journals', 'local');

        return DB::transaction(function () use ($data, $attachmentPath, $userId): JournalEntry {
            $entry = JournalEntry::create([
                'number' => null,
                'entry_date' => $data['entry_date'],
                'description' => $data['description'],
                'status' => JournalStatus::Draft,
                'source_type' => JournalSource::Manual,
                'attachment_path' => is_string($attachmentPath) ? $attachmentPath : null,
                'created_by_user_id' => $userId,
                'posted_at' => null,
            ]);

            /** @var array<int, array{account_id: int|string, debit: string, credit: string, student_id?: int|string|null, memo?: string|null}> $lines */
            $lines = $data['lines'];

            foreach ($lines as $line) {
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
    }

    /**
     * Post a new manual journal entry directly.
     *
     * @param  array<string, mixed>  $data
     */
    public function createAndPost(array $data, ?UploadedFile $attachment = null, ?int $userId = null): JournalEntry
    {
        $attachmentPath = $attachment?->store('journals', 'local');

        /** @var array<int, array{account_id: int|string, debit: string, credit: string, student_id?: int|string|null, memo?: string|null}> $rawLines */
        $rawLines = $data['lines'];

        $lines = array_values(array_map(
            fn (array $l): PostableLine => new PostableLine(
                accountId: (int) $l['account_id'],
                debit: $l['debit'],
                credit: $l['credit'],
                studentId: ! empty($l['student_id']) ? (int) $l['student_id'] : null,
                memo: $l['memo'] ?? null,
            ),
            $rawLines
        ));

        $entry = new PostableEntry(
            entryDate: $data['entry_date'],
            description: $data['description'],
            sourceType: JournalSource::Manual,
            lines: $lines,
            attachmentPath: is_string($attachmentPath) ? $attachmentPath : null,
            createdByUserId: $userId,
        );

        return DB::transaction(fn (): JournalEntry => $this->poster->post($entry));
    }

    /**
     * Update an existing draft journal entry.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(JournalEntry $journal, array $data, ?UploadedFile $attachment = null, bool $hasAttachmentFile = false): JournalEntry
    {
        if ($hasAttachmentFile) {
            if ($journal->attachment_path) {
                Storage::disk('local')->delete($journal->attachment_path);
            }
            $uploaded = $attachment?->store('journals', 'local');
            $attachmentPath = is_string($uploaded) ? $uploaded : null;
        } else {
            $attachmentPath = $journal->attachment_path;
        }

        DB::transaction(function () use ($data, $journal, $attachmentPath): void {
            $journal->update([
                'entry_date' => $data['entry_date'],
                'description' => $data['description'],
                'attachment_path' => $attachmentPath,
            ]);

            $journal->lines()->delete();

            /** @var array<int, array{account_id: int|string, debit: string, credit: string, student_id?: int|string|null, memo?: string|null}> $lines */
            $lines = $data['lines'];

            foreach ($lines as $line) {
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

        return $journal;
    }

    /**
     * Update and post an existing draft journal entry.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateAndPost(JournalEntry $journal, array $data, ?UploadedFile $attachment = null, bool $hasAttachmentFile = false, ?int $userId = null): JournalEntry
    {
        if ($hasAttachmentFile) {
            if ($journal->attachment_path) {
                Storage::disk('local')->delete($journal->attachment_path);
            }
            $uploaded = $attachment?->store('journals', 'local');
            $attachmentPath = is_string($uploaded) ? $uploaded : null;
        } else {
            $attachmentPath = $journal->attachment_path;
        }

        /** @var array<int, array{account_id: int|string, debit: string, credit: string, student_id?: int|string|null, memo?: string|null}> $rawLines */
        $rawLines = $data['lines'];

        $lines = array_values(array_map(
            fn (array $l): PostableLine => new PostableLine(
                accountId: (int) $l['account_id'],
                debit: $l['debit'],
                credit: $l['credit'],
                studentId: ! empty($l['student_id']) ? (int) $l['student_id'] : null,
                memo: $l['memo'] ?? null,
            ),
            $rawLines
        ));

        $postableEntry = new PostableEntry(
            entryDate: $data['entry_date'],
            description: $data['description'],
            sourceType: JournalSource::Manual,
            lines: $lines,
            attachmentPath: $attachmentPath,
            createdByUserId: $userId,
        );

        return DB::transaction(function () use ($journal, $postableEntry): JournalEntry {
            $journal->lines()->delete();
            $journal->delete();

            return $this->poster->post($postableEntry);
        });
    }

    /**
     * Post an existing draft journal entry as-is.
     */
    public function postDraft(JournalEntry $journal, ?int $userId = null): JournalEntry
    {
        $journal->loadMissing('lines');

        $lines = array_values($journal->lines->map(
            fn (JournalLine $l): PostableLine => new PostableLine(
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
            createdByUserId: $userId,
        );

        return DB::transaction(function () use ($journal, $postableEntry): JournalEntry {
            $journal->lines()->delete();
            $journal->delete();

            return $this->poster->post($postableEntry);
        });
    }

    /**
     * Delete a draft journal entry and its attachment if present.
     */
    public function deleteDraft(JournalEntry $journal): void
    {
        if ($journal->attachment_path) {
            Storage::disk('local')->delete($journal->attachment_path);
        }

        $journal->lines()->delete();
        $journal->delete();
    }

    /**
     * Reverse a posted journal entry.
     */
    public function reverse(JournalEntry $journal, string $reason, ?string $date = null): JournalEntry
    {
        $targetDate = ! empty($date)
            ? Carbon::parse($date)
            : Carbon::parse($this->settings->todayDate());

        return DB::transaction(fn (): JournalEntry => $this->poster->reverse(
            original: $journal,
            on: $targetDate,
            reason: $reason,
        ));
    }
}

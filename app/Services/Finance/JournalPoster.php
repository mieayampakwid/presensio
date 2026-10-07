<?php

namespace App\Services\Finance;

use App\Enums\JournalSource;
use App\Enums\JournalStatus;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Services\Finance\Exceptions\ClosedPeriodException;
use App\Services\Finance\Exceptions\InvalidLineException;
use App\Services\Finance\Exceptions\UnbalancedEntryException;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

class JournalPoster
{
    public function __construct(
        private readonly PeriodService $periodService,
        private readonly JournalNumberGenerator $numberGenerator,
    ) {}

    /**
     * Post a balanced journal entry and its lines to the general ledger.
     * Enforces double-entry balance, active/postable accounts, open periods,
     * and entry number generation inside the caller's database transaction.
     *
     * @throws LogicException
     * @throws ClosedPeriodException
     * @throws InvalidLineException
     * @throws UnbalancedEntryException
     */
    public function post(PostableEntry $entry): JournalEntry
    {
        if (DB::transactionLevel() <= 0) {
            throw new LogicException('JournalPoster::post must be called inside an active database transaction.');
        }

        $entryDate = is_string($entry->entryDate)
            ? Carbon::parse($entry->entryDate)->startOfDay()
            : $entry->entryDate->copy()->startOfDay();

        if (! $this->periodService->isOpen($entryDate)) {
            throw new ClosedPeriodException(
                "Periode akuntansi untuk tanggal {$entryDate->toDateString()} telah ditutup."
            );
        }

        if (count($entry->lines) < 2) {
            throw new InvalidLineException('Entri jurnal minimal harus memiliki 2 baris (satu debit dan satu kredit).');
        }

        // Validate accounts
        $accountIds = array_map(fn (PostableLine $line) => $line->accountId, $entry->lines);
        $accounts = Account::whereIn('id', $accountIds)->get()->keyBy('id');

        $totalDebit = '0.00';
        $totalCredit = '0.00';

        foreach ($entry->lines as $index => $line) {
            /** @var Account|null $account */
            $account = $accounts->get($line->accountId);
            if (! $account) {
                throw new InvalidLineException('Akun pada baris #'.($index + 1).' tidak ditemukan.');
            }

            if (! $account->is_active) {
                throw new InvalidLineException("Akun {$account->code} ({$account->name}) tidak aktif.");
            }

            if (! $account->is_postable) {
                throw new InvalidLineException("Akun {$account->code} ({$account->name}) bukan akun postable.");
            }

            $debit = Money::toDecimal($line->debit);
            $credit = Money::toDecimal($line->credit);

            if (Money::isNegative($debit) || Money::isNegative($credit)) {
                throw new InvalidLineException('Nilai debit atau kredit pada baris #'.($index + 1).' tidak boleh bernilai negatif.');
            }

            $hasDebit = Money::isPositive($debit);
            $hasCredit = Money::isPositive($credit);

            if ($hasDebit && $hasCredit) {
                throw new InvalidLineException('Baris #'.($index + 1).' tidak boleh memiliki debit dan kredit sekaligus.');
            }

            if (! $hasDebit && ! $hasCredit) {
                throw new InvalidLineException('Baris #'.($index + 1).' harus memiliki debit atau kredit lebih besar dari 0.');
            }

            $totalDebit = Money::add($totalDebit, $debit);
            $totalCredit = Money::add($totalCredit, $credit);
        }

        if (Money::cmp($totalDebit, $totalCredit) !== 0) {
            throw new UnbalancedEntryException(
                "Jurnal tidak seimbang: Total Debit ({$totalDebit}) tidak sama dengan Total Kredit ({$totalCredit})."
            );
        }

        $number = $this->numberGenerator->generate($entryDate);

        $journalEntry = JournalEntry::create([
            'number' => $number,
            'entry_date' => $entryDate,
            'description' => $entry->description,
            'status' => JournalStatus::Posted,
            'source_type' => $entry->sourceType,
            'source_id' => $entry->sourceId,
            'reverses_entry_id' => $entry->reversesEntryId,
            'attachment_path' => $entry->attachmentPath,
            'created_by_user_id' => $entry->createdByUserId,
            'posted_at' => now(),
        ]);

        foreach ($entry->lines as $line) {
            JournalLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $line->accountId,
                'debit' => Money::toDecimal($line->debit),
                'credit' => Money::toDecimal($line->credit),
                'student_id' => $line->studentId,
                'bill_id' => $line->billId,
                'memo' => $line->memo,
            ]);
        }

        return $journalEntry->load('lines');
    }

    /**
     * Create a reversing journal entry that mirrors every line of the original entry.
     * The original entry is untouched. If the reversal target date's period is closed,
     * the entry is posted on the start of the next open period with the original date noted.
     */
    public function reverse(JournalEntry $original, CarbonInterface $on, string $reason): JournalEntry
    {
        if (DB::transactionLevel() <= 0) {
            throw new LogicException('JournalPoster::reverse must be called inside an active database transaction.');
        }

        if (! $original->isPosted()) {
            throw new LogicException('Hanya jurnal yang berstatus posted yang dapat dibalik.');
        }

        $original->loadMissing('lines');

        $onDate = $on->copy()->startOfDay();
        $isPeriodClosed = ! $this->periodService->isOpen($onDate);

        $postingDate = $isPeriodClosed
            ? $this->periodService->nextOpenPeriodStart($onDate)
            : $onDate;

        $sourceType = match ($original->source_type) {
            JournalSource::Payment => JournalSource::PaymentVoid,
            JournalSource::BillRecognition => JournalSource::BillCancellation,
            default => JournalSource::Manual,
        };

        $reversalLines = [];
        foreach ($original->lines as $line) {
            $memoPrefix = $isPeriodClosed ? "Tanggal asli: {$onDate->toDateString()} | " : '';
            $lineMemo = trim($memoPrefix.($line->memo ?? ''));

            $reversalLines[] = new PostableLine(
                accountId: $line->account_id,
                debit: $line->credit, // Mirrored: credit becomes debit
                credit: $line->debit, // Mirrored: debit becomes credit
                studentId: $line->student_id,
                billId: $line->bill_id,
                memo: $lineMemo ?: null,
            );
        }

        $reversalEntry = new PostableEntry(
            entryDate: $postingDate,
            description: "Pembalik: {$original->number} — {$reason}",
            sourceType: $sourceType,
            lines: $reversalLines,
            sourceId: $original->source_id,
            reversesEntryId: $original->id,
            attachmentPath: null,
            createdByUserId: auth()->id(),
        );

        return $this->post($reversalEntry);
    }
}

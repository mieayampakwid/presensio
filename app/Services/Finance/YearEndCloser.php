<?php

namespace App\Services\Finance;

use App\Enums\AccountType;
use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Services\Audit\AuditLogger;
use App\Services\SchoolSettings;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class YearEndCloser
{
    public function __construct(
        private readonly PeriodService $periodService,
        private readonly JournalPoster $journalPoster,
        private readonly SchoolSettings $settings,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Close a fiscal year: post one closing entry (revenue and expense balances to the
     * net-assets closing account), close the last month, then lock all 12 periods.
     * The "year is closed" marker is any locked period in the year (set for all 12 at once),
     * so a year with all-zero balances is still recorded as closed without an empty entry.
     * Everything runs in one transaction; a failed precondition leaves nothing posted or locked.
     *
     * @throws RuntimeException
     */
    public function close(string|CarbonInterface $fiscalYearStart, int $userId): ?JournalEntry
    {
        $start = Carbon::parse($fiscalYearStart)->startOfDay();

        if ($start->day !== 1 || $start->month !== $this->startMonth()) {
            throw new RuntimeException('Tanggal awal tahun buku tidak valid.');
        }

        $closingAccountId = $this->settings->row()->net_assets_closing_account_id;

        if ($closingAccountId === null || ! Account::query()->whereKey($closingAccountId)->exists()) {
            throw new RuntimeException('Akun penutup aset neto belum diatur pada pengaturan sekolah.');
        }

        $keys = $this->periodKeys($start);
        $end = $start->copy()->addMonthsNoOverflow(11)->endOfMonth()->startOfDay();

        return DB::transaction(function () use ($keys, $start, $end, $closingAccountId, $userId) {
            foreach ($keys as $key) {
                $this->periodService->ensure($key);
            }

            $periods = AccountingPeriod::query()
                ->whereIn('period', $keys)
                ->lockForUpdate()
                ->get()
                ->keyBy('period');

            if ($periods->contains(fn (AccountingPeriod $period) => $period->isLocked())) {
                throw new RuntimeException('Tahun buku sudah ditutup.');
            }

            $lastKey = array_pop($keys);

            foreach ($keys as $key) {
                if (! $periods[$key]->isClosed()) {
                    throw new RuntimeException("Periode {$key} harus sudah ditutup sebelum tutup buku tahunan.");
                }
            }

            if (! $periods[$lastKey]->isOpen()) {
                throw new RuntimeException("Periode terakhir tahun buku ({$lastKey}) harus berstatus terbuka.");
            }

            if (($blocking = $this->unclosedEarlierYear($start)) !== null) {
                $blockingKeys = $this->periodKeys($blocking);

                throw new RuntimeException("Tahun buku {$blockingKeys[0]} s.d. {$blockingKeys[11]} harus ditutup terlebih dahulu.");
            }

            $entry = $this->postClosingEntry($start, $end, $closingAccountId, $userId);

            $lastPeriod = $this->periodService->close($lastKey, $userId);

            AccountingPeriod::query()->whereIn('period', [...$keys, $lastKey])->update(['status' => 'locked']);

            $this->auditLogger->record(
                $lastPeriod,
                'year_end_close',
                ['status' => 'closed'],
                ['status' => 'locked', 'fiscal_year_start' => $start->toDateString(), 'journal_entry_id' => $entry?->id],
                null
            );

            return $entry;
        });
    }

    /**
     * Fiscal years (existing period rows plus the current one) that are not locked yet.
     *
     * @return list<array{fiscal_year_start: string, label: string, is_closable: bool, reason: string|null}>
     */
    public function closableYears(): array
    {
        $statuses = AccountingPeriod::query()->pluck('status', 'period');
        $accountConfigured = $this->settings->row()->net_assets_closing_account_id !== null;

        $starts = $statuses->keys()
            ->map(fn (string $key) => $this->fiscalYearStartFor(Carbon::parse($key.'-01')))
            ->push($this->fiscalYearStartFor(Carbon::parse($this->settings->todayDate())))
            ->unique(fn (CarbonInterface $start) => $start->toDateString())
            ->sortByDesc(fn (CarbonInterface $start) => $start->timestamp);

        $years = [];

        foreach ($starts as $start) {
            $keys = $this->periodKeys($start);

            if (collect($keys)->contains(fn (string $key) => ($statuses[$key] ?? 'open') === 'locked')) {
                continue;
            }

            $lastKey = array_pop($keys);
            $reason = null;

            if (! $accountConfigured) {
                $reason = 'Akun penutup aset neto belum diatur.';
            } elseif (($openKey = collect($keys)->first(fn (string $key) => ($statuses[$key] ?? 'open') !== 'closed')) !== null) {
                $reason = "Periode {$openKey} belum ditutup.";
            } elseif (($statuses[$lastKey] ?? 'open') !== 'open') {
                $reason = "Periode terakhir ({$lastKey}) harus berstatus terbuka.";
            } elseif (($blocking = $this->unclosedEarlierYear($start)) !== null) {
                $blockingKeys = $this->periodKeys($blocking);
                $reason = "Tahun buku sebelumnya ({$blockingKeys[0]} s.d. {$blockingKeys[11]}) belum ditutup.";
            }

            $years[] = [
                'fiscal_year_start' => $start->toDateString(),
                'label' => $keys[0].' s.d. '.$lastKey,
                'is_closable' => $reason === null,
                'reason' => $reason,
            ];
        }

        return $years;
    }

    /**
     * Start of the earliest fiscal year before $start that has posted revenue/expense activity
     * (closing entries ignored) and no locked period, or null. Years without activity are skipped.
     */
    private function unclosedEarlierYear(Carbon $start): ?Carbon
    {
        $firstActivity = $this->activityQuery()
            ->whereDate('journal_entries.entry_date', '<', $start->toDateString())
            ->min('journal_entries.entry_date');

        if ($firstActivity === null) {
            return null;
        }

        for ($year = $this->fiscalYearStartFor(Carbon::parse($firstActivity)); $year->lt($start); $year = $year->copy()->addYear()) {
            $yearEnd = $year->copy()->addMonthsNoOverflow(11)->endOfMonth();

            $hasActivity = $this->activityQuery()
                ->whereDate('journal_entries.entry_date', '>=', $year->toDateString())
                ->whereDate('journal_entries.entry_date', '<=', $yearEnd->toDateString())
                ->exists();

            if ($hasActivity && ! AccountingPeriod::query()->whereIn('period', $this->periodKeys($year))->where('status', 'locked')->exists()) {
                return $year;
            }
        }

        return null;
    }

    private function activityQuery(): Builder
    {
        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->whereIn('accounts.type', [AccountType::Revenue->value, AccountType::Expense->value])
            ->where('journal_entries.status', 'posted')
            ->where('journal_entries.source_type', '!=', JournalSource::Closing->value);
    }

    /**
     * Post the closing entry dated the last day of the fiscal year, or null when every balance is zero.
     */
    private function postClosingEntry(Carbon $start, Carbon $end, int $closingAccountId, int $userId): ?JournalEntry
    {
        $balances = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->whereIn('accounts.type', [AccountType::Revenue->value, AccountType::Expense->value])
            ->where('journal_entries.status', 'posted')
            ->where('journal_entries.source_type', '!=', JournalSource::Closing->value)
            ->whereDate('journal_entries.entry_date', '>=', $start->toDateString())
            ->whereDate('journal_entries.entry_date', '<=', $end->toDateString())
            ->groupBy('journal_lines.account_id')
            ->orderBy('journal_lines.account_id')
            ->selectRaw('journal_lines.account_id, SUM(journal_lines.debit) as total_debit, SUM(journal_lines.credit) as total_credit')
            ->get();

        $lines = [];
        $totalDebit = '0.00';
        $totalCredit = '0.00';

        foreach ($balances as $row) {
            $net = Money::sub($row->total_debit, $row->total_credit);

            if (Money::isZero($net)) {
                continue;
            }

            if (Money::isPositive($net)) {
                $lines[] = new PostableLine(accountId: $row->account_id, credit: $net);
                $totalCredit = Money::add($totalCredit, $net);
            } else {
                $amount = Money::abs($net);
                $lines[] = new PostableLine(accountId: $row->account_id, debit: $amount);
                $totalDebit = Money::add($totalDebit, $amount);
            }
        }

        if ($lines === []) {
            return null;
        }

        $difference = Money::sub($totalDebit, $totalCredit);

        $lines[] = Money::isPositive($difference)
            ? new PostableLine(accountId: $closingAccountId, credit: $difference)
            : new PostableLine(accountId: $closingAccountId, debit: Money::abs($difference));

        return $this->journalPoster->post(new PostableEntry(
            entryDate: $end,
            description: 'Tutup buku tahun '.$start->format('Y-m').' s.d. '.$end->format('Y-m'),
            sourceType: JournalSource::Closing,
            lines: $lines,
            createdByUserId: $userId,
        ));
    }

    private function startMonth(): int
    {
        return (int) $this->settings->row()->fiscal_year_start_month;
    }

    private function fiscalYearStartFor(Carbon $date): Carbon
    {
        $year = $date->month >= $this->startMonth() ? $date->year : $date->year - 1;

        return Carbon::create($year, $this->startMonth(), 1)->startOfDay();
    }

    /**
     * @return list<string>
     */
    private function periodKeys(Carbon $start): array
    {
        return array_map(
            fn (int $offset) => $start->copy()->addMonthsNoOverflow($offset)->format('Y-m'),
            range(0, 11),
        );
    }
}

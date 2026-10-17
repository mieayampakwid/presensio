<?php

namespace App\Services\Finance;

use App\Enums\JournalSource;
use App\Enums\JournalStatus;
use App\Enums\PaymentStatus;
use App\Models\Account;
use App\Models\Bill;
use App\Models\FeeType;
use App\Models\Student;
use App\Services\SchoolSettings;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Receivables reconciliation (spec 14 §Reconciliation invariant): GL versus bill subledger.
 *
 * Per receivable account: GL = Σ(debit − credit) of posted lines dated ≤ as-of; subledger = Σ over bills
 * recognized by as-of and not cancelled / written off by as-of of (net − verified allocations).
 * Prepaid revenue: GL = Σ(credit − debit); subledger = Σ verified allocations on bills not yet recognized
 * (and still open) at as-of. Difference = GL − subledger; any non-zero difference is a defect.
 *
 * Past as-of dates are reconstructed from dated facts: recognized_at, the dates of the cancellation /
 * write-off / payment / void journal entries (falling back to the stamp timestamps when no entry exists),
 * and net amounts rewound by discount-adjustment entries posted after as-of. Limitation: a discount change
 * made on an unrecognized bill, or edits that bypass the ledger, are not rewound.
 *
 * Bills are streamed in chunks and everything else is grouped SQL. The per-bill mismatch list is capped
 * at MISMATCH_LIMIT rows; upgrade path: paginate it if finance needs to page through a large breakage.
 */
class ReceivablesReconciliation
{
    private const MISMATCH_LIMIT = 50;

    public function __construct(private readonly SchoolSettings $settings) {}

    /**
     * @return array{
     *     as_of: string,
     *     receivables: list<array{account_id: int|null, code: string|null, name: string|null, gl_balance: string, subledger_balance: string, difference: string, is_balanced: bool}>,
     *     prepaid: array{account_id: int|null, code: string|null, name: string|null, gl_balance: string, subledger_balance: string, difference: string, is_balanced: bool},
     *     total_difference: string,
     *     is_balanced: bool,
     *     mismatches: list<array{bill_id: int, student_name: string|null, title: string, account_code: string, kind: string, expected: string, actual: string}>,
     *     mismatch_count: int
     * }
     */
    public function asOf(CarbonInterface|string $date): array
    {
        $asOf = is_string($date) ? Date::parse($date)->toDateString() : $date->toDateString();
        $schoolEnd = Date::parse($asOf, $this->settings->timezone())->endOfDay()->utc()->toDateTimeString();

        $receivableByFeeType = FeeType::query()->pluck('receivable_account_id', 'id')->all();
        $receivableIds = array_values(array_unique($receivableByFeeType));
        $prepaidId = $this->settings->row()->prepaid_revenue_account_id;
        $accountIds = array_filter([...$receivableIds, $prepaidId]);

        $glTotals = $this->glTotals($asOf, $accountIds);
        $glByBill = $this->glByBill($asOf, $accountIds);
        $paid = $this->verifiedPaid($asOf, $schoolEnd);
        $closedOn = $this->closedDates();
        $rewind = $this->adjustmentRewind($asOf, $receivableIds);

        $receivableSub = array_fill_keys($receivableIds, '0.00');
        $prepaidSub = '0.00';
        $mismatches = [];
        $mismatchCount = 0;

        Bill::query()
            ->select(['id', 'student_id', 'fee_type_id', 'title', 'amount', 'discount_amount', 'recognized_at', 'cancelled_at', 'written_off_at'])
            ->chunkById(1000, function ($bills) use ($asOf, $receivableByFeeType, $prepaidId, $paid, $closedOn, $rewind, $glByBill, &$receivableSub, &$prepaidSub, &$mismatches, &$mismatchCount) {
                foreach ($bills as $bill) {
                    $closedDate = $closedOn[$bill->id] ?? $this->stampDate($bill);
                    $isOpen = $closedDate === null || $closedDate > $asOf;
                    $isRecognized = $bill->recognized_at !== null && $bill->recognized_at->toDateString() <= $asOf;
                    $billPaid = $paid[$bill->id] ?? '0.00';
                    $accountId = $receivableByFeeType[$bill->fee_type_id];

                    $expectedReceivable = '0.00';
                    $expectedPrepaid = '0.00';

                    if ($isOpen && $isRecognized) {
                        $net = Money::add(Money::sub($bill->amount, $bill->discount_amount), $rewind[$bill->id] ?? '0.00');
                        $expectedReceivable = Money::sub($net, $billPaid);
                    } elseif ($isOpen) {
                        $expectedPrepaid = $billPaid;
                    }

                    $receivableSub[$accountId] = Money::add($receivableSub[$accountId], $expectedReceivable);
                    $prepaidSub = Money::add($prepaidSub, $expectedPrepaid);

                    $actualReceivable = $glByBill[$bill->id][$accountId] ?? '0.00';
                    $actualPrepaid = Money::sub('0', $glByBill[$bill->id][$prepaidId] ?? '0.00');

                    foreach ([['receivable', $accountId, $expectedReceivable, $actualReceivable], ['prepaid', $prepaidId, $expectedPrepaid, $actualPrepaid]] as [$kind, $mismatchAccountId, $expected, $actual]) {
                        if (Money::cmp($expected, $actual) === 0) {
                            continue;
                        }

                        $mismatchCount++;

                        if (count($mismatches) < self::MISMATCH_LIMIT) {
                            $mismatches[] = [
                                'bill_id' => $bill->id,
                                'student_id' => $bill->student_id,
                                'title' => $bill->title,
                                'account_id' => $mismatchAccountId,
                                'kind' => $kind,
                                'expected' => $expected,
                                'actual' => $actual,
                            ];
                        }
                    }
                }
            });

        $accounts = Account::query()->whereIn('id', $accountIds)->get(['id', 'code', 'name'])->keyBy('id');

        $receivables = array_map(fn (int $id) => $this->row(
            $accounts[$id],
            $glTotals[$id] ?? '0.00',
            $receivableSub[$id],
        ), $receivableIds);

        $prepaid = $this->row(
            $prepaidId === null ? null : $accounts[$prepaidId],
            Money::sub('0', $glTotals[$prepaidId] ?? '0.00'),
            $prepaidSub,
        );

        $totalDifference = array_reduce(
            [...$receivables, $prepaid],
            fn (string $sum, array $row) => Money::add($sum, Money::abs($row['difference'])),
            '0.00',
        );

        return [
            'as_of' => $asOf,
            'receivables' => $receivables,
            'prepaid' => $prepaid,
            'total_difference' => $totalDifference,
            'is_balanced' => Money::isZero($totalDifference),
            'mismatches' => $this->describe($mismatches, $accounts),
            'mismatch_count' => $mismatchCount,
        ];
    }

    /**
     * @return array{account_id: int|null, code: string|null, name: string|null, gl_balance: string, subledger_balance: string, difference: string, is_balanced: bool}
     */
    private function row(?Account $account, string $gl, string $subledger): array
    {
        $difference = Money::sub($gl, $subledger);

        return [
            'account_id' => $account?->id,
            'code' => $account?->code,
            'name' => $account?->name,
            'gl_balance' => $gl,
            'subledger_balance' => $subledger,
            'difference' => $difference,
            'is_balanced' => Money::isZero($difference),
        ];
    }

    /**
     * @param  list<array{bill_id: int, student_id: int, title: string, account_id: int|null, kind: string, expected: string, actual: string}>  $mismatches
     * @param  Collection<int, Account>  $accounts
     * @return list<array{bill_id: int, student_name: string|null, title: string, account_code: string, kind: string, expected: string, actual: string}>
     */
    private function describe(array $mismatches, $accounts): array
    {
        $students = Student::query()->whereIn('id', array_column($mismatches, 'student_id'))->pluck('full_name', 'id');

        return array_map(fn (array $row) => [
            'bill_id' => $row['bill_id'],
            'student_name' => $students[$row['student_id']] ?? null,
            'title' => $row['title'],
            'account_code' => $row['account_id'] === null ? '-' : $accounts[$row['account_id']]->code,
            'kind' => $row['kind'],
            'expected' => $row['expected'],
            'actual' => $row['actual'],
        ], $mismatches);
    }

    /**
     * Posted ledger lines dated ≤ as-of.
     */
    private function postedLines(string $asOf): Builder
    {
        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.status', JournalStatus::Posted->value)
            ->whereDate('journal_entries.entry_date', '<=', $asOf);
    }

    /**
     * Debit − credit per account.
     *
     * @param  array<int, int>  $accountIds
     * @return array<int, string>
     */
    private function glTotals(string $asOf, array $accountIds): array
    {
        $totals = [];

        $rows = $this->postedLines($asOf)
            ->whereIn('journal_lines.account_id', $accountIds)
            ->groupBy('journal_lines.account_id')
            ->selectRaw('journal_lines.account_id, sum(journal_lines.debit) as debit, sum(journal_lines.credit) as credit')
            ->get();

        foreach ($rows as $row) {
            $totals[$row->account_id] = Money::sub($row->debit, $row->credit);
        }

        return $totals;
    }

    /**
     * Debit − credit per bill and account.
     *
     * @param  array<int, int>  $accountIds
     * @return array<int, array<int, string>>
     */
    private function glByBill(string $asOf, array $accountIds): array
    {
        $balances = [];

        $rows = $this->postedLines($asOf)
            ->whereNotNull('journal_lines.bill_id')
            ->whereIn('journal_lines.account_id', $accountIds)
            ->groupBy('journal_lines.bill_id', 'journal_lines.account_id')
            ->selectRaw('journal_lines.bill_id, journal_lines.account_id, sum(journal_lines.debit) as debit, sum(journal_lines.credit) as credit')
            ->get();

        foreach ($rows as $row) {
            $balances[$row->bill_id][$row->account_id] = Money::sub($row->debit, $row->credit);
        }

        return $balances;
    }

    /**
     * Allocations on payments verified by as-of and not yet voided by then. A payment's date is that of its
     * ledger entry (reversal entry for a void), falling back to the verified / voided timestamp.
     *
     * @return array<int, string>
     */
    private function verifiedPaid(string $asOf, string $schoolEnd): array
    {
        $paid = [];

        $rows = DB::table('payment_allocations as pa')
            ->join('payments as p', 'p.id', '=', 'pa.payment_id')
            ->leftJoin('journal_entries as e', function ($join) {
                $join->on('e.source_id', '=', 'p.id')
                    ->where('e.source_type', JournalSource::Payment->value)
                    ->where('e.status', JournalStatus::Posted->value)
                    ->whereNull('e.reverses_entry_id');
            })
            ->leftJoin('journal_entries as r', function ($join) {
                $join->on('r.reverses_entry_id', '=', 'e.id')->where('r.status', JournalStatus::Posted->value);
            })
            ->whereIn('p.status', [PaymentStatus::Verified->value, PaymentStatus::Void->value])
            ->where(fn ($query) => $query
                ->where(fn ($q) => $q->whereNotNull('e.id')->whereDate('e.entry_date', '<=', $asOf))
                ->orWhere(fn ($q) => $q->whereNull('e.id')->where('p.verified_at', '<=', $schoolEnd)))
            ->where(fn ($query) => $query
                ->where('p.status', PaymentStatus::Verified->value)
                ->orWhere(fn ($q) => $q->whereNotNull('r.id')->whereDate('r.entry_date', '>', $asOf))
                ->orWhere(fn ($q) => $q->whereNull('r.id')->where('p.voided_at', '>', $schoolEnd)))
            ->groupBy('pa.bill_id')
            ->selectRaw('pa.bill_id, sum(pa.amount) as paid')
            ->get();

        foreach ($rows as $row) {
            $paid[$row->bill_id] = Money::toDecimal($row->paid);
        }

        return $paid;
    }

    /**
     * Date of the cancellation / write-off ledger entry per bill.
     *
     * @return array<int, string>
     */
    private function closedDates(): array
    {
        return DB::table('journal_entries')
            ->whereIn('source_type', [JournalSource::BillCancellation->value, JournalSource::WriteOff->value])
            ->where('status', JournalStatus::Posted->value)
            ->groupBy('source_id')
            ->selectRaw('source_id, min(entry_date) as closed_on')
            ->pluck('closed_on', 'source_id')
            ->map(fn ($date) => Date::parse($date)->toDateString())
            ->all();
    }

    /**
     * School-timezone date of a cancellation / write-off stamp, for bills with no ledger entry.
     */
    private function stampDate(Bill $bill): ?string
    {
        $stamp = $bill->cancelled_at ?? $bill->written_off_at;

        return $stamp?->copy()->tz($this->settings->timezone())->toDateString();
    }

    /**
     * Net amount change to undo for discount adjustments posted after as-of (credit − debit on the receivable).
     *
     * @param  array<int, int>  $receivableIds
     * @return array<int, string>
     */
    private function adjustmentRewind(string $asOf, array $receivableIds): array
    {
        $rewind = [];

        $rows = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.status', JournalStatus::Posted->value)
            ->where('journal_entries.source_type', JournalSource::BillAdjustment->value)
            ->whereDate('journal_entries.entry_date', '>', $asOf)
            ->whereIn('journal_lines.account_id', $receivableIds)
            ->groupBy('journal_lines.bill_id')
            ->selectRaw('journal_lines.bill_id, sum(journal_lines.debit) as debit, sum(journal_lines.credit) as credit')
            ->get();

        foreach ($rows as $row) {
            $rewind[$row->bill_id] = Money::sub($row->credit, $row->debit);
        }

        return $rewind;
    }
}

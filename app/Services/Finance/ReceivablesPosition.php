<?php

namespace App\Services\Finance;

use App\Enums\JournalSource;
use App\Enums\JournalStatus;
use App\Enums\PaymentStatus;
use App\Models\Bill;
use App\Services\SchoolSettings;
use App\Support\Money;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Receivable facts of the bill subledger as of a date, shared by the reconciliation and the receivable
 * reports. Past dates are reconstructed from dated facts (see ReceivablesReconciliation).
 */
class ReceivablesPosition
{
    public function __construct(private readonly SchoolSettings $settings) {}

    /**
     * End of the school-timezone day, in UTC, for comparing against timestamp columns.
     */
    public function schoolEnd(string $asOf): string
    {
        return Date::parse($asOf, $this->settings->timezone())->endOfDay()->utc()->toDateTimeString();
    }

    /**
     * Bills recognized by as-of and still open (not cancelled / written off) with a positive remaining
     * amount (net − verified allocations, discount adjustments after as-of rewound), optionally for one student.
     *
     * @return list<array{bill_id: int, student_id: int, title: string, due_date: string, remaining: string}>
     */
    public function openBills(string $asOf, ?int $studentId = null): array
    {
        $paid = $this->verifiedPaid($asOf, $this->schoolEnd($asOf));
        $closedOn = $this->closedDates();
        $rewind = $this->adjustmentRewind($asOf, DB::table('fee_types')->pluck('receivable_account_id')->unique()->values()->all());
        $open = [];

        Bill::query()
            ->select(['id', 'student_id', 'title', 'amount', 'discount_amount', 'due_date', 'recognized_at', 'cancelled_at', 'written_off_at'])
            ->whereNotNull('recognized_at')
            ->whereDate('recognized_at', '<=', $asOf)
            ->when($studentId !== null, fn ($query) => $query->where('student_id', $studentId))
            ->chunkById(1000, function ($bills) use ($asOf, $paid, $closedOn, $rewind, &$open) {
                foreach ($bills as $bill) {
                    $closedDate = $closedOn[$bill->id] ?? $this->stampDate($bill);

                    if ($closedDate !== null && $closedDate <= $asOf) {
                        continue;
                    }

                    $net = Money::add(Money::sub($bill->amount, $bill->discount_amount), $rewind[$bill->id] ?? '0.00');
                    $remaining = Money::sub($net, $paid[$bill->id] ?? '0.00');

                    if (Money::isPositive($remaining)) {
                        $open[] = [
                            'bill_id' => $bill->id,
                            'student_id' => $bill->student_id,
                            'title' => $bill->title,
                            'due_date' => $bill->due_date->toDateString(),
                            'remaining' => $remaining,
                        ];
                    }
                }
            });

        return $open;
    }

    /**
     * The class each student was enrolled in on the date (enrollment history, never a stored pointer).
     * Students without an enrollment covering the date are absent from the result.
     *
     * @param  array<int, int>  $studentIds
     * @return array<int, array{id: int, name: string}>
     */
    public function classesOn(string $date, array $studentIds): array
    {
        $classes = [];

        foreach (array_chunk(array_values(array_unique($studentIds)), 500) as $chunk) {
            $rows = DB::table('enrollments')
                ->join('classes', 'classes.id', '=', 'enrollments.class_id')
                ->whereIn('enrollments.student_id', $chunk)
                ->whereDate('enrollments.started_on', '<=', $date)
                ->where(fn ($query) => $query->whereNull('enrollments.ended_on')->orWhereDate('enrollments.ended_on', '>=', $date))
                ->get(['enrollments.student_id', 'classes.id as class_id', 'classes.name']);

            foreach ($rows as $row) {
                $classes[$row->student_id] = ['id' => $row->class_id, 'name' => $row->name];
            }
        }

        return $classes;
    }

    /**
     * Allocations on payments verified by as-of and not yet voided by then. A payment's date is that of its
     * ledger entry (reversal entry for a void), falling back to the verified / voided timestamp.
     *
     * @return array<int, string>
     */
    public function verifiedPaid(string $asOf, string $schoolEnd): array
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
    public function closedDates(): array
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
    public function stampDate(Bill $bill): ?string
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
    public function adjustmentRewind(string $asOf, array $receivableIds): array
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

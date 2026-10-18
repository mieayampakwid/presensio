<?php

namespace App\Services\Finance\Reports;

use App\Enums\JournalSource;
use App\Enums\JournalStatus;
use App\Enums\PaymentStatus;
use App\Models\Bill;
use App\Models\FeeType;
use App\Models\Student;
use App\Services\Finance\ReceivablesPosition;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Student receivable card (Kartu Piutang Siswa, spec 14 Req. 12). The running balance is built from the
 * student's posted journal lines on the receivable accounts up to as-of, so it matches the GL; the bill
 * subledger total (Σ open recognized remaining) is shown next to it and a mismatch is flagged.
 */
class StudentReceivableCardReport
{
    private const SEARCH_LIMIT = 10;

    public function __construct(private readonly ReceivablesPosition $position) {}

    /**
     * @return array{
     *     as_of: string,
     *     term: string,
     *     results: list<array{id: int, name: string, student_number: string|null, class_name: string|null}>,
     *     student: array{id: int, name: string, student_number: string|null, class_name: string|null}|null,
     *     bills: list<array<string, mixed>>,
     *     ledger: list<array<string, mixed>>,
     *     balance: string,
     *     subledger: string,
     *     is_balanced: bool
     * }
     */
    public function generate(?int $studentId, string $asOf, string $term = ''): array
    {
        $term = trim($term);
        $results = mb_strlen($term) >= 2
            ? array_values(Student::query()
                ->where(fn ($query) => $query->whereLike('full_name', "%{$term}%")->orWhereLike('student_number', "%{$term}%"))
                ->with('currentEnrollment.schoolClass')
                ->orderBy('full_name')
                ->limit(self::SEARCH_LIMIT)
                ->get()
                ->map(fn (Student $student) => $this->summary($student, $student->schoolClass?->name))
                ->all())
            : [];

        $base = ['as_of' => $asOf, 'term' => $term, 'results' => $results];
        $student = $studentId === null ? null : Student::query()->find($studentId);

        if ($student === null) {
            return [...$base, 'student' => null, 'bills' => [], 'ledger' => [], 'balance' => '0.00', 'subledger' => '0.00', 'is_balanced' => true];
        }

        $open = collect($this->position->openBills($asOf, $student->id))->keyBy('bill_id');
        $ledger = $this->ledger($student->id, $asOf);
        $balance = $ledger === [] ? '0.00' : end($ledger)['balance'];
        $subledger = $open->reduce(fn (string $sum, array $bill) => Money::add($sum, $bill['remaining']), '0.00');

        return [
            ...$base,
            'student' => $this->summary($student, $this->position->classesOn($asOf, [$student->id])[$student->id]['name'] ?? ReceivablesAgingReport::ALUMNI_LABEL),
            'bills' => $this->bills($student->id, $asOf, $open->all()),
            'ledger' => $ledger,
            'balance' => $balance,
            'subledger' => $subledger,
            'is_balanced' => Money::cmp($balance, $subledger) === 0,
        ];
    }

    /**
     * Stream CSV export of the running-balance ledger with UTF-8 BOM.
     */
    public function toCsv(int $studentId, string $asOf): StreamedResponse
    {
        $data = $this->generate($studentId, $asOf);

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Tanggal', 'No. Jurnal', 'Keterangan', 'Sumber', 'Tagihan', 'Debit', 'Kredit', 'Saldo']);

            foreach ($data['ledger'] as $row) {
                fputcsv($out, [$row['date'], $row['number'], $row['description'], $row['source'], $row['bill_title'], $row['debit'], $row['credit'], $row['balance']]);
            }

            fputcsv($out, ['Saldo Buku Besar', '', '', '', '', '', '', $data['balance']]);
            fputcsv($out, ['Saldo Subledger', '', '', '', '', '', '', $data['subledger']]);

            fclose($out);
        }, 'kartu-piutang-'.($data['student']['student_number'] ?? $data['student']['id'] ?? 'siswa').'-per-'.$data['as_of'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{id: int, name: string, student_number: string|null, class_name: string|null}
     */
    private function summary(Student $student, ?string $className): array
    {
        return [
            'id' => $student->id,
            'name' => $student->full_name,
            'student_number' => $student->student_number,
            'class_name' => $className,
        ];
    }

    /**
     * Posted receivable-account lines up to as-of, oldest first, with the running debit − credit balance.
     *
     * @return list<array{date: string, number: string|null, description: string, source: string, bill_title: string|null, debit: string, credit: string, balance: string}>
     */
    private function ledger(int $studentId, string $asOf): array
    {
        $titles = Bill::query()->where('student_id', $studentId)->pluck('title', 'id');

        $lines = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.student_id', $studentId)
            ->whereIn('journal_lines.account_id', FeeType::query()->select('receivable_account_id'))
            ->where('journal_entries.status', JournalStatus::Posted->value)
            ->whereDate('journal_entries.entry_date', '<=', $asOf)
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_lines.id')
            ->get(['journal_entries.entry_date', 'journal_entries.number', 'journal_entries.description', 'journal_entries.source_type', 'journal_lines.bill_id', 'journal_lines.debit', 'journal_lines.credit']);

        $balance = '0.00';

        return array_values($lines->map(function ($line) use (&$balance, $titles) {
            $balance = Money::add($balance, Money::sub($line->debit, $line->credit));

            return [
                'date' => substr((string) $line->entry_date, 0, 10),
                'number' => $line->number,
                'description' => $line->description,
                'source' => JournalSource::tryFrom($line->source_type)?->label() ?? $line->source_type,
                'bill_title' => $titles[$line->bill_id] ?? null,
                'debit' => Money::toDecimal($line->debit),
                'credit' => Money::toDecimal($line->credit),
                'balance' => $balance,
            ];
        })->all());
    }

    /**
     * Bills recognized by as-of with the verified allocations (payments dated by as-of) and as-of state.
     *
     * @param  array<int, array{bill_id: int, remaining: string}>  $open
     * @return list<array<string, mixed>>
     */
    private function bills(int $studentId, string $asOf, array $open): array
    {
        $closedOn = $this->position->closedDates();

        return array_values(Bill::query()
            ->where('student_id', $studentId)
            ->whereNotNull('recognized_at')
            ->whereDate('recognized_at', '<=', $asOf)
            ->with(['allocations' => fn ($query) => $query
                ->whereHas('payment', fn ($q) => $q->where('status', PaymentStatus::Verified->value)->whereDate('payment_date', '<=', $asOf))
                ->with('payment:id,receipt_number,payment_date')])
            ->orderBy('due_date')
            ->orderBy('id')
            ->get()
            ->map(function (Bill $bill) use ($open, $closedOn, $asOf) {
                $closedDate = $closedOn[$bill->id] ?? $this->position->stampDate($bill);
                $isClosed = $closedDate !== null && $closedDate <= $asOf;
                $allocations = $bill->allocations->map(fn ($allocation) => [
                    'receipt_number' => $allocation->payment->receipt_number,
                    'payment_date' => $allocation->payment->payment_date->toDateString(),
                    'amount' => $allocation->amount,
                ])->all();

                return [
                    'id' => $bill->id,
                    'title' => $bill->title,
                    'period_key' => $bill->period_key,
                    'due_date' => $bill->due_date->toDateString(),
                    'net_amount' => $bill->netAmount(),
                    'status' => match (true) {
                        $isClosed => $bill->cancelled_at !== null ? 'Dibatalkan' : 'Dihapusbukukan',
                        ! isset($open[$bill->id]) => 'Lunas',
                        $allocations !== [] => 'Dibayar Sebagian',
                        default => 'Belum Dibayar',
                    },
                    'closed_on' => $isClosed ? $closedDate : null,
                    'remaining_amount' => $open[$bill->id]['remaining'] ?? '0.00',
                    'allocations' => $allocations,
                ];
            })
            ->all());
    }
}

<?php

namespace App\Services\Finance\Reports;

use App\Models\Bill;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\Finance\ReceivablesPosition;
use App\Services\SchoolSettings;
use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Receivables aging (Umur Piutang): open recognized bills by days past due on the as-of date, grouped by the
 * class the student was in on that date ("Alumni / Keluar" when there was none), plus a needs-review list of
 * students without a current enrollment who still owe on a partially paid bill (spec 14 Req. 12).
 */
class ReceivablesAgingReport
{
    public const BUCKETS = [
        'current' => 'Belum jatuh tempo',
        'd1_30' => '1-30 hari',
        'd31_60' => '31-60 hari',
        'd61_90' => '61-90 hari',
        'd90_plus' => '> 90 hari',
    ];

    public const ALUMNI_LABEL = 'Alumni / Keluar';

    public function __construct(
        private readonly ReceivablesPosition $position,
        private readonly SchoolSettings $settings,
    ) {}

    /**
     * @return array{
     *     as_of: string,
     *     class_id: int|null,
     *     class_options: list<array{id: int, name: string}>,
     *     classes: list<array{class_id: int|null, name: string, students: list<array<string, mixed>>, subtotals: array<string, string>}>,
     *     totals: array<string, string>,
     *     needs_review: list<array{student_id: int, student_name: string, student_number: string|null, bill_id: int, title: string, due_date: string, paid_amount: string, remaining_amount: string}>
     * }
     */
    public function generate(string $asOf, ?int $classId = null): array
    {
        $open = $this->position->openBills($asOf);
        $classOf = $this->position->classesOn($asOf, array_column($open, 'student_id'));
        $students = Student::query()->whereIn('id', array_column($open, 'student_id'))->get(['id', 'full_name', 'student_number'])->keyBy('id');

        $groups = [];

        foreach ($open as $bill) {
            $class = $classOf[$bill['student_id']] ?? null;

            if ($classId !== null && ($class['id'] ?? null) !== $classId) {
                continue;
            }

            $key = $class['id'] ?? 0;
            $bucket = $this->bucket($bill['due_date'], $asOf);
            $student = $students[$bill['student_id']];

            $groups[$key]['name'] = $class['name'] ?? self::ALUMNI_LABEL;
            $groups[$key]['students'][$student->id] ??= [
                'student_id' => $student->id,
                'student_name' => $student->full_name,
                'student_number' => $student->student_number,
                ...$this->emptyBuckets(),
            ];
            $row = &$groups[$key]['students'][$student->id];
            $row[$bucket] = Money::add($row[$bucket], $bill['remaining']);
            $row['total'] = Money::add($row['total'], $bill['remaining']);
            unset($row);
        }

        uasort($groups, fn (array $a, array $b) => [$a['name'] === self::ALUMNI_LABEL, $a['name']] <=> [$b['name'] === self::ALUMNI_LABEL, $b['name']]);

        $totals = $this->emptyBuckets();
        $classes = [];

        foreach ($groups as $key => $group) {
            $students = array_values($group['students']);
            usort($students, fn (array $a, array $b) => $a['student_name'] <=> $b['student_name']);
            $subtotals = $this->sum($students);
            $totals = $this->sum([$totals, $subtotals]);
            $classes[] = ['class_id' => $key === 0 ? null : $key, 'name' => $group['name'], 'students' => $students, 'subtotals' => $subtotals];
        }

        return [
            'as_of' => $asOf,
            'class_id' => $classId,
            'class_options' => array_values(SchoolClass::query()->orderBy('name')->get(['id', 'name'])->map(fn (SchoolClass $class) => ['id' => $class->id, 'name' => $class->name])->all()),
            'classes' => $classes,
            'totals' => $totals,
            'needs_review' => $this->needsReview(),
        ];
    }

    /**
     * Stream CSV export with UTF-8 BOM.
     */
    public function toCsv(string $asOf, ?int $classId = null): StreamedResponse
    {
        $data = $this->generate($asOf, $classId);

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Kelas', 'Siswa', 'NIS', ...array_values(self::BUCKETS), 'Total']);

            foreach ($data['classes'] as $class) {
                foreach ($class['students'] as $student) {
                    fputcsv($out, [$class['name'], $student['student_name'], $student['student_number'], ...$this->bucketValues($student)]);
                }

                fputcsv($out, [$class['name'], 'Subtotal', '', ...$this->bucketValues($class['subtotals'])]);
            }

            fputcsv($out, ['Total', '', '', ...$this->bucketValues($data['totals'])]);

            fputcsv($out, []);
            fputcsv($out, ['Perlu ditinjau']);
            fputcsv($out, ['Siswa', 'NIS', 'Tagihan', 'Jatuh Tempo', 'Dibayar', 'Sisa']);

            foreach ($data['needs_review'] as $row) {
                fputcsv($out, [$row['student_name'], $row['student_number'], $row['title'], $row['due_date'], $row['paid_amount'], $row['remaining_amount']]);
            }

            fclose($out);
        }, 'umur-piutang-per-'.$data['as_of'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Bucket key by days past due: due on or after as-of is current, then 1-30, 31-60, 61-90, over 90.
     */
    public function bucket(string $dueDate, string $asOf): string
    {
        $daysPastDue = (int) Date::parse($dueDate)->diffInDays(Date::parse($asOf), false);

        return match (true) {
            $daysPastDue <= 0 => 'current',
            $daysPastDue <= 30 => 'd1_30',
            $daysPastDue <= 60 => 'd31_60',
            $daysPastDue <= 90 => 'd61_90',
            default => 'd90_plus',
        };
    }

    /**
     * Open recognized bills with a partial payment whose student has no enrollment covering today.
     * Cached `paid_amount` is used, so this is a current-state list and takes no as-of date.
     *
     * @return list<array{student_id: int, student_name: string, student_number: string|null, bill_id: int, title: string, due_date: string, paid_amount: string, remaining_amount: string}>
     */
    private function needsReview(): array
    {
        $today = $this->settings->todayDate();

        return array_values(Bill::query()
            ->with('student:id,full_name,student_number')
            ->whereNotNull('recognized_at')
            ->whereNull('cancelled_at')
            ->whereNull('written_off_at')
            ->where('paid_amount', '>', 0)
            ->whereRaw('amount - discount_amount - paid_amount > 0')
            ->whereDoesntHave('student.enrollments', fn (Builder $query) => $query
                ->whereDate('started_on', '<=', $today)
                ->where(fn ($q) => $q->whereNull('ended_on')->orWhereDate('ended_on', '>=', $today)))
            ->orderBy('student_id')
            ->orderBy('due_date')
            ->get()
            ->map(fn (Bill $bill) => [
                'student_id' => $bill->student_id,
                'student_name' => $bill->student->full_name,
                'student_number' => $bill->student->student_number,
                'bill_id' => $bill->id,
                'title' => $bill->title,
                'due_date' => $bill->due_date->toDateString(),
                'paid_amount' => $bill->paid_amount,
                'remaining_amount' => $bill->remainingAmount(),
            ])
            ->all());
    }

    /**
     * @return array<string, string>
     */
    private function emptyBuckets(): array
    {
        return array_fill_keys([...array_keys(self::BUCKETS), 'total'], '0.00');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, string>
     */
    private function sum(array $rows): array
    {
        $sum = $this->emptyBuckets();

        foreach ($rows as $row) {
            foreach (array_keys($sum) as $key) {
                $sum[$key] = Money::add($sum[$key], $row[$key]);
            }
        }

        return $sum;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function bucketValues(array $row): array
    {
        return array_map(fn (string $key) => $row[$key], [...array_keys(self::BUCKETS), 'total']);
    }
}

<?php

namespace App\Services\Finance\Reports;

use App\Enums\PaymentStatus;
use App\Services\Finance\ReceivablesPosition;
use App\Services\SchoolSettings;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Collections versus arrears per class (spec 14 Req. 12, principal view). Collected = verified allocations
 * by payment date, attributed to the class the student was in on that date; arrears = open remaining as of
 * the range end, attributed to the class on that date. "Alumni / Keluar" when there was no enrollment.
 */
class CollectionsByClassReport
{
    public function __construct(
        private readonly ReceivablesPosition $position,
        private readonly SchoolSettings $settings,
    ) {}

    /**
     * Default range: first day of the current month to today (school timezone).
     *
     * @return array{from: string, to: string}
     */
    public function defaultRange(): array
    {
        $today = $this->settings->todayDate();

        return ['from' => substr($today, 0, 8).'01', 'to' => $today];
    }

    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     classes: list<array{class_id: int|null, name: string, paying_students: int, collected: string, arrears: string}>,
     *     totals: array{paying_students: int, collected: string, arrears: string}
     * }
     */
    public function generate(string $from, string $to): array
    {
        $rows = DB::table('payment_allocations as pa')
            ->join('payments as p', 'p.id', '=', 'pa.payment_id')
            ->join('bills as b', 'b.id', '=', 'pa.bill_id')
            ->where('p.status', PaymentStatus::Verified->value)
            ->whereDate('p.payment_date', '>=', $from)
            ->whereDate('p.payment_date', '<=', $to)
            ->groupBy('b.student_id', 'p.payment_date')
            ->selectRaw('b.student_id, p.payment_date, sum(pa.amount) as collected')
            ->get();

        $groups = [];
        $payers = [];

        foreach ($rows->groupBy(fn ($row) => substr((string) $row->payment_date, 0, 10)) as $date => $dayRows) {
            $classOf = $this->position->classesOn($date, $dayRows->map(fn ($row) => (int) $row->student_id)->values()->all());

            foreach ($dayRows as $row) {
                $key = $classOf[$row->student_id]['id'] ?? 0;
                $groups[$key]['name'] = $classOf[$row->student_id]['name'] ?? ReceivablesAgingReport::ALUMNI_LABEL;
                $groups[$key]['payers'][$row->student_id] = true;
                $groups[$key]['collected'] = Money::add($groups[$key]['collected'] ?? '0.00', $row->collected);
                $payers[$row->student_id] = true;
            }
        }

        $open = $this->position->openBills($to);
        $classOf = $this->position->classesOn($to, array_column($open, 'student_id'));

        foreach ($open as $bill) {
            $key = $classOf[$bill['student_id']]['id'] ?? 0;
            $groups[$key]['name'] ??= $classOf[$bill['student_id']]['name'] ?? ReceivablesAgingReport::ALUMNI_LABEL;
            $groups[$key]['arrears'] = Money::add($groups[$key]['arrears'] ?? '0.00', $bill['remaining']);
        }

        uasort($groups, fn (array $a, array $b) => [$a['name'] === ReceivablesAgingReport::ALUMNI_LABEL, $a['name']] <=> [$b['name'] === ReceivablesAgingReport::ALUMNI_LABEL, $b['name']]);

        $classes = [];
        $totals = ['paying_students' => count($payers), 'collected' => '0.00', 'arrears' => '0.00'];

        foreach ($groups as $key => $group) {
            $classes[] = [
                'class_id' => $key === 0 ? null : $key,
                'name' => $group['name'],
                'paying_students' => count($group['payers'] ?? []),
                'collected' => $group['collected'] ?? '0.00',
                'arrears' => $group['arrears'] ?? '0.00',
            ];
            $totals['collected'] = Money::add($totals['collected'], $group['collected'] ?? '0.00');
            $totals['arrears'] = Money::add($totals['arrears'], $group['arrears'] ?? '0.00');
        }

        return ['from' => $from, 'to' => $to, 'classes' => $classes, 'totals' => $totals];
    }

    /**
     * Stream CSV export with UTF-8 BOM.
     */
    public function toCsv(string $from, string $to): StreamedResponse
    {
        $data = $this->generate($from, $to);

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Kelas', 'Siswa Membayar', 'Terkumpul', 'Piutang']);

            foreach ($data['classes'] as $class) {
                fputcsv($out, [$class['name'], $class['paying_students'], $class['collected'], $class['arrears']]);
            }

            fputcsv($out, ['Total', $data['totals']['paying_students'], $data['totals']['collected'], $data['totals']['arrears']]);

            fclose($out);
        }, 'penerimaan-per-kelas-'.$data['from'].'-sd-'.$data['to'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

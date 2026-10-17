<?php

namespace App\Services\Finance;

use App\Enums\FeeKind;
use App\Models\AcademicYear;
use App\Models\Bill;
use App\Models\FeeType;
use App\Models\Student;
use App\Models\StudentFeeDiscount;
use App\Services\Finance\Exceptions\BillOperationException;
use App\Services\SchoolSettings;
use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Bulk bill generation (spec 14 Requirement 5). Students are resolved through enrollments only.
 *
 * @phpstan-type Input array<string, mixed>
 * @phpstan-type Summary array{created: int, skipped: int, discounted: int}
 */
class BillGenerator
{
    public function __construct(
        private readonly BillService $billService,
        private readonly BillStatusCalculator $calculator,
        private readonly SchoolSettings $settings,
    ) {}

    /**
     * Count what a generation would create without writing anything.
     *
     * @param  Input  $input
     * @return Summary
     */
    public function preview(array $input): array
    {
        return $this->generate($input, false);
    }

    /**
     * Create the bills in one transaction and recognize those whose date has been reached.
     *
     * @param  Input  $input
     * @return Summary
     */
    public function commit(array $input): array
    {
        return DB::transaction(fn () => $this->generate($input, true));
    }

    /**
     * @param  Input  $input
     * @return Summary
     */
    private function generate(array $input, bool $commit): array
    {
        $year = AcademicYear::active() ?? throw new BillOperationException('Tidak ada tahun ajaran aktif.');

        // Serializes concurrent runs for the same fee type so the existence check below stays valid.
        $feeType = FeeType::query()->when($commit, fn (Builder $query) => $query->lockForUpdate())->findOrFail((int) $input['fee_type_id']);
        $amount = Money::toDecimal($input['amount'] ?? $feeType->default_amount);
        $periods = $this->periods($feeType, $input);
        $students = $this->students($input, $year);

        $existing = Bill::query()
            ->where('fee_type_id', $feeType->id)
            ->whereIn('period_key', array_keys($periods))
            ->whereIn('student_id', $students->modelKeys())
            ->get(['student_id', 'period_key'])
            ->mapWithKeys(fn (Bill $bill) => [$bill->student_id.'|'.$bill->period_key => true]);

        $discounts = StudentFeeDiscount::query()
            ->where('fee_type_id', $feeType->id)
            ->where('academic_year_id', $year->id)
            ->get()
            ->keyBy('student_id');

        $summary = ['created' => 0, 'skipped' => 0, 'discounted' => 0];

        foreach ($students as $student) {
            $discount = $this->discountAmount($discounts->get($student->id), $amount);

            foreach ($periods as $periodKey => $period) {
                if ($existing->has($student->id.'|'.$periodKey)) {
                    $summary['skipped']++;

                    continue;
                }

                $summary['created']++;
                $summary['discounted'] += Money::isPositive($discount) ? 1 : 0;

                if ($commit) {
                    $this->create($student, $feeType, $year, $periodKey, $period, $amount, $discount, $discounts->get($student->id));
                }
            }
        }

        return $summary;
    }

    /**
     * @param  array{title: string, due_date: string}  $period
     */
    private function create(Student $student, FeeType $feeType, AcademicYear $year, string $periodKey, array $period, string $amount, string $discount, ?StudentFeeDiscount $rule): void
    {
        $bill = new Bill([
            'student_id' => $student->id,
            'fee_type_id' => $feeType->id,
            'academic_year_id' => $year->id,
            'period_key' => $periodKey,
            'title' => $period['title'],
            'amount' => $amount,
            'discount_amount' => $discount,
            'discount_reason' => Money::isPositive($discount) ? $rule?->reason : null,
            'paid_amount' => '0.00',
            'due_date' => $period['due_date'],
        ]);
        $bill->status = $this->calculator->statusFor($bill, '0.00');
        $bill->save();

        $this->billService->recognize($bill);
    }

    /**
     * Period key => title and due date. Recurring fees span the month range; one-time fees use 'once'.
     *
     * @param  Input  $input
     * @return array<string, array{title: string, due_date: string}>
     */
    private function periods(FeeType $feeType, array $input): array
    {
        if ($feeType->type === FeeKind::OneTime) {
            return ['once' => ['title' => $feeType->name, 'due_date' => (string) $input['due_date']]];
        }

        $periods = [];
        $cursor = Date::parse($input['from_month'].'-01');
        $last = Date::parse($input['to_month'].'-01');

        while ($cursor->lessThanOrEqualTo($last)) {
            $month = $cursor->settings(['locale' => 'id']);
            $periods[$cursor->format('Y-m')] = [
                'title' => $feeType->name.' '.$month->translatedFormat('F Y'),
                'due_date' => $cursor->setDay(min((int) $input['due_day'], $cursor->daysInMonth))->toDateString(),
            ];
            $cursor = $cursor->addMonth();
        }

        return $periods;
    }

    /**
     * Students with an enrollment today in an active-year class matching the target.
     *
     * @param  Input  $input
     * @return Collection<int, Student>
     */
    private function students(array $input, AcademicYear $year): Collection
    {
        $today = $this->settings->todayDate();

        return Student::query()
            ->whereHas('enrollments', function (Builder $enrollment) use ($input, $year, $today) {
                $enrollment->where('started_on', '<=', $today)
                    ->where(fn (Builder $open) => $open->whereNull('ended_on')->orWhere('ended_on', '>=', $today))
                    ->whereHas('schoolClass', function (Builder $class) use ($input, $year) {
                        $class->where('academic_year_id', $year->id)
                            ->when($input['target'] === 'class', fn (Builder $query) => $query->where('id', $input['class_id']))
                            ->when($input['target'] === 'grade_level', fn (Builder $query) => $query->where('grade_level', $input['grade_level']));
                    });
            })
            ->orderBy('id')
            ->get();
    }

    private function discountAmount(?StudentFeeDiscount $rule, string $amount): string
    {
        if ($rule === null) {
            return '0.00';
        }

        $discount = $rule->percent !== null
            ? bcdiv(Money::mul($amount, $rule->percent), '100', 2)
            : Money::toDecimal($rule->fixed_amount);

        return Money::gt($discount, $amount) ? $amount : $discount;
    }
}

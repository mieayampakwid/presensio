<?php

namespace Tests\Feature\Fees;

use App\Enums\BillStatus;
use App\Enums\FeeKind;
use App\Enums\JournalSource;
use App\Models\AcademicYear;
use App\Models\Account;
use App\Models\Bill;
use App\Models\FeeType;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\AcademicYears\RollOverService;
use App\Services\EnrollmentService;
use App\Services\Finance\BillService;
use App\Services\SchoolSettings;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class WithdrawalCancellationTest extends TestCase
{
    use RefreshDatabase;

    private const REASON = 'Siswa keluar/lulus';

    private FeeType $feeType;

    private Student $student;

    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        Date::setTestNow('2026-12-20 03:00:00');

        app(SchoolSettings::class)->row();
        $this->seed(ChartOfAccountsSeeder::class);
        app(SchoolSettings::class)->refresh();

        $this->feeType = FeeType::factory()->create([
            'receivable_account_id' => $this->accountId('1-1300'),
            'revenue_account_id' => $this->accountId('4-1100'),
            'discount_account_id' => $this->accountId('4-1900'),
        ]);

        $year = AcademicYear::factory()->active()->create(['starts_at' => '2026-07-01', 'ends_at' => '2027-06-30']);
        $this->class = SchoolClass::factory()->create(['academic_year_id' => $year->id]);
        $this->student = Student::factory()->enrolledIn($this->class, '2026-07-01')->create();
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    private function accountId(string $code): int
    {
        return Account::where('code', $code)->firstOrFail()->id;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function bill(string $period, array $attributes = []): Bill
    {
        return Bill::factory()->create([
            'student_id' => $this->student->id,
            'fee_type_id' => $this->feeType->id,
            'period_key' => $period,
            'due_date' => $period.'-10',
            ...$attributes,
        ]);
    }

    private function pay(Bill $bill, string $amount): void
    {
        PaymentAllocation::factory()->for(Payment::factory()->verified(), 'payment')->for($bill)->create(['amount' => $amount]);
        app(BillService::class)->recompute($bill);
    }

    private function assertCancelled(Bill $bill): void
    {
        $fresh = $bill->fresh();
        $this->assertSame(BillStatus::Cancelled, $fresh->status);
        $this->assertSame(self::REASON, $fresh->cancel_reason);
    }

    public function test_release_cancels_future_zero_payment_bills_and_lists_partial_ones_ac_14_11(): void
    {
        $november = $this->bill('2026-11');
        $december = $this->bill('2026-12');
        $january = $this->bill('2027-01');
        $partial = $this->bill('2026-12', ['fee_type_id' => FeeType::factory()->create()->id]);
        $this->pay($partial, '100000.00');
        $this->student->refresh();

        $review = app(EnrollmentService::class)->release($this->student, '2026-11-15');

        $this->assertNotNull($this->student->enrollments()->first()->ended_on);
        $this->assertCancelled($december);
        $this->assertCancelled($january);
        $this->assertSame(BillStatus::Unpaid, $november->fresh()->status);
        $this->assertSame(BillStatus::PartiallyPaid, $partial->fresh()->status);
        $this->assertNull($partial->fresh()->cancelled_at);
        $this->assertSame([$partial->id], array_map(fn (Bill $bill) => $bill->id, $review));
    }

    public function test_cancelling_a_recognized_future_bill_reverses_its_recognition(): void
    {
        $december = $this->bill('2026-12');
        app(BillService::class)->recognize($december);

        app(EnrollmentService::class)->release($this->student, '2026-11-15');

        $this->assertCancelled($december);
        $cancellation = JournalEntry::where('source_type', JournalSource::BillCancellation)->where('source_id', $december->id)->firstOrFail();
        $this->assertSame(
            JournalEntry::where('source_type', JournalSource::BillRecognition)->where('source_id', $december->id)->value('id'),
            $cancellation->reverses_entry_id,
        );
        $this->assertSame((string) $cancellation->lines->sum('debit'), (string) $cancellation->lines->sum('credit'));
    }

    public function test_one_time_bills_are_decided_by_due_date(): void
    {
        $oneTime = FeeType::factory()->create(['type' => FeeKind::OneTime]);
        $before = $this->bill('2026-12', ['fee_type_id' => $oneTime->id, 'due_date' => '2026-11-10']);
        $after = $this->bill('2026-11', ['fee_type_id' => $oneTime->id, 'due_date' => '2026-12-10']);

        app(EnrollmentService::class)->release($this->student, '2026-11-15');

        $this->assertSame(BillStatus::Unpaid, $before->fresh()->status);
        $this->assertCancelled($after);
    }

    public function test_promotion_with_successor_does_not_cancel_bills(): void
    {
        $december = $this->bill('2026-12');
        $nextClass = SchoolClass::factory()->create(['academic_year_id' => $this->class->academic_year_id]);

        app(EnrollmentService::class)->assign($this->student, $nextClass, '2026-11-16');

        $this->assertSame(BillStatus::Unpaid, $december->fresh()->status);
    }

    public function test_roll_over_promotion_keeps_bills_and_unmapped_alumni_have_future_bills_cancelled(): void
    {
        $alumnus = Student::factory()->enrolledIn($this->class, '2026-07-01')->create();
        $promotedBill = $this->bill('2027-07');
        $alumnusBill = $this->bill('2027-07', ['student_id' => $alumnus->id]);
        $alumnusPast = $this->bill('2026-12', ['student_id' => $alumnus->id]);
        $alumnusPartial = $this->bill('2027-08', ['student_id' => $alumnus->id]);
        $this->pay($alumnusPartial, '50000.00');

        $target = AcademicYear::factory()->create(['starts_at' => '2027-07-01', 'ends_at' => '2028-06-30']);
        $targetClass = SchoolClass::factory()->create(['academic_year_id' => $target->id]);
        $alumnusClass = SchoolClass::factory()->create(['academic_year_id' => $this->class->academic_year_id]);
        $alumnus->enrollments()->update(['class_id' => $alumnusClass->id]);

        $summary = app(RollOverService::class)->rollOver($target, [
            $this->class->id => ['mode' => 'existing', 'target_class_id' => $targetClass->id],
            $alumnusClass->id => ['mode' => 'none'],
        ], '2027-07-01');

        $this->assertSame(BillStatus::Unpaid, $promotedBill->fresh()->status);
        $this->assertCancelled($alumnusBill);
        $this->assertSame(BillStatus::Unpaid, $alumnusPast->fresh()->status);
        $this->assertSame(BillStatus::PartiallyPaid, $alumnusPartial->fresh()->status);
        $this->assertSame(1, $summary['needs_review']);
    }
}

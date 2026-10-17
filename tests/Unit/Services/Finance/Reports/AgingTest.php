<?php

namespace Tests\Unit\Services\Finance\Reports;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Enrollment;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\Finance\BillService;
use App\Services\Finance\PaymentService;
use App\Services\Finance\Reports\ReceivablesAgingReport;
use App\Services\SchoolSettings;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AgingTest extends TestCase
{
    use RefreshDatabase;

    private FeeType $feeType;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Date::setTestNow('2026-07-10 03:00:00');

        app(SchoolSettings::class)->row();
        $this->seed(ChartOfAccountsSeeder::class);
        app(SchoolSettings::class)->refresh();

        $this->user = User::factory()->finance()->create();
        $this->feeType = FeeType::factory()->create([
            'receivable_account_id' => $this->accountId('1-1300'),
            'revenue_account_id' => $this->accountId('4-1100'),
            'discount_account_id' => $this->accountId('4-1900'),
        ]);
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

    private function bill(string $dueDate, ?Student $student = null, string $period = '2026-07'): Bill
    {
        $bill = Bill::factory()->create([
            'student_id' => ($student ?? Student::factory()->create())->id,
            'fee_type_id' => FeeType::factory()->create([
                'receivable_account_id' => $this->feeType->receivable_account_id,
                'revenue_account_id' => $this->feeType->revenue_account_id,
                'discount_account_id' => $this->feeType->discount_account_id,
            ])->id,
            'period_key' => $period,
            'amount' => '350000.00',
            'due_date' => $dueDate,
        ]);
        app(BillService::class)->recognize($bill);

        return $bill->fresh();
    }

    private function pay(Bill $bill, string $amount): void
    {
        app(PaymentService::class)->createCash([$bill->id => $amount], [$bill->student_id], $this->accountId('1-1100'), $this->user);
    }

    private function enroll(Student $student, string $className, string $from = '2026-01-01', ?string $to = null): void
    {
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'class_id' => (SchoolClass::where('name', $className)->first() ?? SchoolClass::factory()->create(['name' => $className]))->id,
            'started_on' => $from,
            'ended_on' => $to,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function aging(string $asOf = '2026-07-10', ?int $classId = null): array
    {
        return app(ReceivablesAgingReport::class)->generate($asOf, $classId);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function bucketBoundaries(): array
    {
        return [
            'due on as-of' => ['2026-07-10', 'current'],
            'due after as-of' => ['2026-07-11', 'current'],
            '1 day late' => ['2026-07-09', 'd1_30'],
            '30 days late' => ['2026-06-10', 'd1_30'],
            '31 days late' => ['2026-06-09', 'd31_60'],
            '60 days late' => ['2026-05-11', 'd31_60'],
            '61 days late' => ['2026-05-10', 'd61_90'],
            '90 days late' => ['2026-04-11', 'd61_90'],
            '91 days late' => ['2026-04-10', 'd90_plus'],
        ];
    }

    #[DataProvider('bucketBoundaries')]
    public function test_days_past_due_fall_into_the_expected_bucket(string $dueDate, string $expected): void
    {
        $this->assertSame($expected, app(ReceivablesAgingReport::class)->bucket($dueDate, '2026-07-10'));
    }

    public function test_only_remaining_of_open_recognized_bills_is_aged(): void
    {
        $student = Student::factory()->create();
        $this->enroll($student, '7A');

        $partial = $this->bill('2026-07-01', $student);
        $this->pay($partial, '100000.00');
        $paid = $this->bill('2026-07-02', $student);
        $this->pay($paid, '350000.00');
        app(BillService::class)->cancel($this->bill('2026-07-03', $student), 'Salah tagih');
        app(BillService::class)->writeOff($this->bill('2026-07-04', $student), 'Tidak tertagih');
        $this->bill('2026-09-10', $student, '2026-09');

        $report = $this->aging();

        $this->assertCount(1, $report['classes']);
        $this->assertSame('250000.00', $report['classes'][0]['students'][0]['d1_30']);
        $this->assertSame('250000.00', $report['totals']['total']);
    }

    public function test_aging_as_of_a_past_date_ignores_later_payments_and_bills(): void
    {
        $student = Student::factory()->create();
        $this->enroll($student, '7A');
        $bill = $this->bill('2026-07-01', $student);

        Date::setTestNow('2026-08-05 03:00:00');
        $this->pay($bill, '350000.00');
        $this->bill('2026-08-01', $student, '2026-08');

        $this->assertSame('350000.00', $this->aging('2026-07-31')['totals']['total']);
        $this->assertSame('350000.00', $this->aging('2026-08-31')['totals']['total']);
    }

    public function test_students_are_grouped_by_class_on_the_as_of_date_with_alumni_last(): void
    {
        $mover = Student::factory()->create();
        $this->enroll($mover, '7A', '2026-01-01', '2026-06-30');
        $this->enroll($mover, '8A', '2026-07-01');
        $alumnus = Student::factory()->create();
        $this->bill('2026-06-15', $mover, '2026-06');
        $this->bill('2026-06-15', $alumnus, '2026-06');

        $past = $this->aging('2026-06-20');
        $now = $this->aging('2026-07-10');

        $this->assertSame(['7A', 'Alumni / Keluar'], array_column($past['classes'], 'name'));
        $this->assertSame(['8A', 'Alumni / Keluar'], array_column($now['classes'], 'name'));
        $this->assertNull($now['classes'][1]['class_id']);
        $this->assertSame('700000.00', $now['totals']['total']);

        $filtered = $this->aging('2026-07-10', $now['classes'][0]['class_id']);
        $this->assertSame(['8A'], array_column($filtered['classes'], 'name'));
        $this->assertSame('350000.00', $filtered['totals']['total']);
    }

    public function test_needs_review_lists_only_unenrolled_students_with_a_partial_payment(): void
    {
        $withdrawnPartial = Student::factory()->create();
        $this->enroll($withdrawnPartial, '7A', '2026-01-01', '2026-06-30');
        $this->pay($this->bill('2026-07-01', $withdrawnPartial), '100000.00');

        $withdrawnUnpaid = Student::factory()->create();
        $this->enroll($withdrawnUnpaid, '7A', '2026-01-01', '2026-06-30');
        $this->bill('2026-07-01', $withdrawnUnpaid);

        $enrolledPartial = Student::factory()->create();
        $this->enroll($enrolledPartial, '7A');
        $this->pay($this->bill('2026-07-01', $enrolledPartial), '100000.00');

        $review = $this->aging()['needs_review'];

        $this->assertCount(1, $review);
        $this->assertSame($withdrawnPartial->id, $review[0]['student_id']);
        $this->assertSame('100000.00', $review[0]['paid_amount']);
        $this->assertSame('250000.00', $review[0]['remaining_amount']);
    }
}

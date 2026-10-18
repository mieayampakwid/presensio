<?php

namespace Tests\Unit\Services\Finance\Reports;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Enrollment;
use App\Models\FeeType;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\Finance\BillService;
use App\Services\Finance\PaymentService;
use App\Services\Finance\Reports\CollectionsByClassReport;
use App\Services\Finance\Reports\StudentReceivableCardReport;
use App\Services\SchoolSettings;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class ReceivableReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Date::setTestNow('2026-07-10 03:00:00');

        app(SchoolSettings::class)->row();
        $this->seed(ChartOfAccountsSeeder::class);
        app(SchoolSettings::class)->refresh();

        $this->user = User::factory()->finance()->create();
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

    private function bill(Student $student, string $period = '2026-07'): Bill
    {
        $bill = Bill::factory()->create([
            'student_id' => $student->id,
            'fee_type_id' => FeeType::factory()->create([
                'receivable_account_id' => $this->accountId('1-1300'),
                'revenue_account_id' => $this->accountId('4-1100'),
                'discount_account_id' => $this->accountId('4-1900'),
            ])->id,
            'period_key' => $period,
            'amount' => '350000.00',
            'due_date' => "{$period}-10",
        ]);
        app(BillService::class)->recognize($bill);

        return $bill->fresh();
    }

    private function pay(Bill $bill, string $amount): Payment
    {
        return app(PaymentService::class)->createCash([$bill->id => $amount], [$bill->student_id], $this->accountId('1-1100'), $this->user);
    }

    private function enroll(Student $student, string $className): void
    {
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'class_id' => (SchoolClass::where('name', $className)->first() ?? SchoolClass::factory()->create(['name' => $className]))->id,
            'started_on' => '2026-01-01',
        ]);
    }

    public function test_card_running_balance_follows_the_ledger_and_ends_at_the_subledger(): void
    {
        $student = Student::factory()->create();
        $this->enroll($student, '7A');
        $service = app(BillService::class);

        $adjusted = $this->bill($student);
        $this->pay($adjusted, '100000.00');
        $service->writeOff($this->bill($student, '2026-06'), 'Tidak tertagih');
        $voided = $this->pay($this->bill($student, '2026-05'), '350000.00');
        app(PaymentService::class)->void($voided, 'Salah input', $this->user);

        Date::setTestNow('2026-08-05 03:00:00');
        $service->adjustDiscount($adjusted->fresh(), '50000.00', 'Penyesuaian');

        $card = app(StudentReceivableCardReport::class);
        $now = $card->generate($student->id, '2026-08-31');
        $beforeAdjustment = $card->generate($student->id, '2026-07-31');

        $sources = array_column($now['ledger'], 'source');
        $this->assertContains('Penyesuaian Tagihan', $sources);
        $this->assertContains('Penghapusan Piutang', $sources);
        $this->assertContains('Pembatalan Pembayaran', $sources);
        $this->assertSame('550000.00', $now['balance']);
        $this->assertSame($now['balance'], end($now['ledger'])['balance']);
        $this->assertSame('550000.00', $now['subledger']);
        $this->assertTrue($now['is_balanced']);
        $this->assertNotContains('Penyesuaian Tagihan', array_column($beforeAdjustment['ledger'], 'source'));
        $this->assertSame('600000.00', $beforeAdjustment['balance']);
        $this->assertTrue($beforeAdjustment['is_balanced']);

        $this->assertSame(['Belum Dibayar', 'Dibayar Sebagian', 'Dihapusbukukan'], collect($now['bills'])->pluck('status')->sort()->values()->all());
        $partial = collect($now['bills'])->firstWhere('status', 'Dibayar Sebagian');
        $this->assertSame('200000.00', $partial['remaining_amount']);
        $this->assertCount(1, $partial['allocations']);
        $this->assertSame('100000.00', $partial['allocations'][0]['amount']);
    }

    public function test_card_flags_a_ledger_that_disagrees_with_the_bill_subledger(): void
    {
        $student = Student::factory()->create();
        $bill = $this->bill($student);
        JournalLine::where('bill_id', $bill->id)->where('account_id', $this->accountId('1-1300'))->delete();

        $card = app(StudentReceivableCardReport::class)->generate($student->id, '2026-07-10');

        $this->assertFalse($card['is_balanced']);
        $this->assertSame('0.00', $card['balance']);
        $this->assertSame('350000.00', $card['subledger']);
    }

    public function test_collections_count_verified_payments_by_class_on_payment_date_next_to_arrears(): void
    {
        $inSevenA = Student::factory()->create();
        $inSevenB = Student::factory()->create();
        $alumnus = Student::factory()->create();
        $this->enroll($inSevenA, '7A');
        $this->enroll($inSevenB, '7B');

        Date::setTestNow('2026-06-20 03:00:00');
        $this->pay($this->bill($inSevenA, '2026-06'), '70000.00');
        Date::setTestNow('2026-07-10 03:00:00');
        $this->pay($this->bill($inSevenA), '100000.00');
        $this->pay($this->bill($inSevenA, '2026-08'), '30000.00');
        $this->pay($this->bill($inSevenB), '50000.00');
        $this->pay($this->bill($alumnus), '20000.00');
        app(PaymentService::class)->void($this->pay($this->bill($inSevenB, '2026-05'), '40000.00'), 'Salah input', $this->user);
        $pendingBill = $this->bill($inSevenA, '2026-04');
        app(PaymentService::class)->submitTransfer('80000.00', [$pendingBill->id => '80000.00'], [$inSevenA->id], $this->accountId('1-1100'), '2026-07-10', $this->user);

        $report = app(CollectionsByClassReport::class)->generate('2026-07-01', '2026-07-31');
        $rows = collect($report['classes'])->keyBy('name');

        $this->assertSame(['7A', '7B', 'Alumni / Keluar'], array_column($report['classes'], 'name'));
        $this->assertSame(1, $rows['7A']['paying_students']);
        $this->assertSame('130000.00', $rows['7A']['collected']);
        $this->assertSame('50000.00', $rows['7B']['collected']);
        $this->assertSame('20000.00', $rows['Alumni / Keluar']['collected']);
        $this->assertSame(3, $report['totals']['paying_students']);
        $this->assertSame('200000.00', $report['totals']['collected']);
        $this->assertSame('880000.00', $rows['7A']['arrears']);
        $this->assertSame('650000.00', $rows['7B']['arrears']);
        $this->assertSame('330000.00', $rows['Alumni / Keluar']['arrears']);
        $this->assertSame('1860000.00', $report['totals']['arrears']);
    }
}

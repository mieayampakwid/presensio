<?php

namespace Tests\Unit\Services\Finance;

use App\Models\Account;
use App\Models\Bill;
use App\Models\FeeType;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\BillService;
use App\Services\Finance\PaymentService;
use App\Services\Finance\ReceivablesReconciliation;
use App\Services\SchoolSettings;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class ReceivablesReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private FeeType $feeType;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Friday 2026-07-10, 10:00 Jakarta.
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

    private function bill(string $period = '2026-07'): Bill
    {
        $bill = Bill::factory()->create([
            'fee_type_id' => $this->feeType->id,
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

    /**
     * @return array<string, mixed>
     */
    private function reconcile(string $asOf = '2026-07-10'): array
    {
        return app(ReceivablesReconciliation::class)->asOf($asOf);
    }

    public function test_recognition_and_payments_reconcile_receivable_and_prepaid_to_zero(): void
    {
        $this->pay($this->bill(), '100000.00');
        $this->pay($this->bill('2026-08'), '50000.00');

        $result = $this->reconcile();

        $this->assertSame('250000.00', $result['receivables'][0]['subledger_balance']);
        $this->assertSame('250000.00', $result['receivables'][0]['gl_balance']);
        $this->assertSame('50000.00', $result['prepaid']['subledger_balance']);
        $this->assertSame('50000.00', $result['prepaid']['gl_balance']);
        $this->assertTrue($result['is_balanced']);
        $this->assertSame([], $result['mismatches']);
    }

    public function test_recognizing_a_prepaid_bill_and_voiding_its_payment_still_reconcile(): void
    {
        $bill = $this->bill('2026-08');
        $payment = $this->pay($bill, '350000.00');

        Date::setTestNow('2026-08-05 03:00:00');
        app(BillService::class)->recognize($bill);
        $this->assertTrue($this->reconcile('2026-08-05')['is_balanced']);

        app(PaymentService::class)->void($payment, 'Salah input', $this->user);

        $result = $this->reconcile('2026-08-05');
        $this->assertTrue($result['is_balanced']);
        $this->assertSame('350000.00', $result['receivables'][0]['subledger_balance']);
    }

    public function test_cancelled_and_written_off_bills_leave_no_receivable(): void
    {
        $service = app(BillService::class);
        $service->cancel($this->bill(), 'Salah tagih');
        $service->writeOff($this->bill(), 'Tidak tertagih');

        $result = $this->reconcile();

        $this->assertSame('0.00', $result['receivables'][0]['gl_balance']);
        $this->assertTrue($result['is_balanced']);
    }

    public function test_missing_journal_line_is_reported_with_the_offending_bill(): void
    {
        $bill = $this->bill();
        JournalLine::where('bill_id', $bill->id)->where('account_id', $this->accountId('1-1300'))->delete();

        $result = $this->reconcile();

        $this->assertFalse($result['is_balanced']);
        $this->assertSame('-350000.00', $result['receivables'][0]['difference']);
        $this->assertSame(1, $result['mismatch_count']);
        $this->assertSame($bill->id, $result['mismatches'][0]['bill_id']);
        $this->assertSame('350000.00', $result['mismatches'][0]['expected']);
        $this->assertSame('0.00', $result['mismatches'][0]['actual']);
    }

    public function test_verified_allocation_without_journal_posting_is_a_difference(): void
    {
        $bill = $this->bill();
        $this->pay($bill, '100000.00');
        JournalLine::where('bill_id', $bill->id)->where('credit', '100000.00')->delete();

        $this->assertSame('100000.00', $this->reconcile()['receivables'][0]['difference']);
    }

    public function test_past_as_of_ignores_later_payment_discount_and_void(): void
    {
        $bill = $this->bill();
        $first = $this->pay($bill, '100000.00');

        Date::setTestNow('2026-08-05 03:00:00');
        app(PaymentService::class)->void($first, 'Salah input', $this->user);
        $this->pay($bill, '50000.00');
        app(BillService::class)->adjustDiscount($bill->fresh(), '50000.00', 'Penyesuaian');

        $past = $this->reconcile('2026-07-31');
        $now = $this->reconcile('2026-08-31');

        $this->assertSame('250000.00', $past['receivables'][0]['subledger_balance']);
        $this->assertTrue($past['is_balanced']);
        $this->assertSame('250000.00', $now['receivables'][0]['subledger_balance']);
        $this->assertTrue($now['is_balanced']);
    }
}

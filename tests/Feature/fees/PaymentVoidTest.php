<?php

namespace Tests\Feature\Fees;

use App\Enums\BillStatus;
use App\Enums\JournalSource;
use App\Enums\PaymentStatus;
use App\Models\AcademicYear;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\FeeType;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\Finance\BillService;
use App\Services\Finance\PaymentService;
use App\Services\Finance\Reports\CashBookReport;
use App\Services\SchoolSettings;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class PaymentVoidTest extends TestCase
{
    use RefreshDatabase;

    private FeeType $spp;

    private User $finance;

    protected function setUp(): void
    {
        parent::setUp();

        Date::setTestNow('2026-09-10 03:00:00');

        app(SchoolSettings::class)->row();
        $this->seed(ChartOfAccountsSeeder::class);
        app(SchoolSettings::class)->refresh();

        $this->spp = FeeType::factory()->create([
            'receivable_account_id' => $this->accountId('1-1300'),
            'revenue_account_id' => $this->accountId('4-1100'),
            'discount_account_id' => $this->accountId('4-1900'),
        ]);
        $this->finance = User::factory()->finance()->create();
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

    private function bill(string $period, ?string $recognizedAt): Bill
    {
        return Bill::factory()->create([
            'student_id' => Student::factory(),
            'fee_type_id' => $this->spp->id,
            'academic_year_id' => AcademicYear::query()->value('id') ?? AcademicYear::factory()->create()->id,
            'period_key' => $period,
            'title' => "SPP {$period}",
            'amount' => '350000.00',
            'due_date' => "{$period}-10",
            'recognized_at' => $recognizedAt,
        ]);
    }

    private function pay(Bill $bill): Payment
    {
        return app(PaymentService::class)->createCash([$bill->id => '350000.00'], [$bill->student_id], $this->accountId('1-1100'), $this->finance);
    }

    /**
     * @return list<array{string, string, string}> [account code, debit, credit]
     */
    private function lines(JournalEntry $entry): array
    {
        return $entry->lines()->with('account')->orderBy('id')->get()
            ->map(fn (JournalLine $line) => [$line->account->code, (string) $line->debit, (string) $line->credit])
            ->all();
    }

    private function balance(string $code, Bill $bill): string
    {
        $lines = JournalLine::where('account_id', $this->accountId($code))->where('bill_id', $bill->id)->get();

        return bcsub((string) $lines->sum('debit'), (string) $lines->sum('credit'), 2);
    }

    public function test_void_requires_a_reason_then_restores_bill_and_cash_book_ac_14_08(): void
    {
        $bill = $this->bill('2026-09', '2026-09-01');
        $payment = $this->pay($bill);
        Date::setTestNow('2026-09-12 03:00:00');

        $this->actingAs($this->finance)->postJson(route('fees.payments.void', $payment), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
        $this->assertSame(PaymentStatus::Verified, $payment->refresh()->status);

        $this->actingAs($this->finance)->post(route('fees.payments.void', $payment), ['reason' => 'Salah input'])->assertRedirect();

        $payment->refresh();
        $this->assertSame(PaymentStatus::Void, $payment->status);
        $this->assertSame('Salah input', $payment->void_reason);
        $this->assertSame($this->finance->id, $payment->voided_by_user_id);
        $this->assertNotNull($payment->voided_at);

        $bill->refresh();
        $this->assertSame(BillStatus::Unpaid, $bill->status);
        $this->assertSame('0.00', $bill->paid_amount);

        $audit = AuditLog::where('action', 'voided')->sole();
        $this->assertSame($payment->id, $audit->auditable_id);
        $this->assertSame('Salah input', $audit->reason);

        // `to` is the next day: sqlite stores entry_date with a time part, so '<= same day' would miss it.
        $book = app(CashBookReport::class);
        $originalDay = $book->generate($this->accountId('1-1100'), '2026-09-10', '2026-09-11');
        $this->assertSame('350000.00', $originalDay['period_debits']);
        $this->assertSame('0.00', $originalDay['period_credits']);

        $voidDay = $book->generate($this->accountId('1-1100'), '2026-09-12', '2026-09-13');
        $this->assertSame('0.00', $voidDay['period_debits']);
        $this->assertSame('350000.00', $voidDay['period_credits']);
        $this->assertSame('0.00', $book->generate($this->accountId('1-1100'))['closing_balance']);
    }

    public function test_void_reverses_without_touching_the_original_entry_ac_14_17(): void
    {
        $bill = $this->bill('2026-09', '2026-09-01');
        $payment = $this->pay($bill);
        $original = JournalEntry::where('source_type', JournalSource::Payment)->where('source_id', $payment->id)->sole();
        $originalLines = $this->lines($original);
        Date::setTestNow('2026-09-12 03:00:00');

        $this->actingAs($this->finance)->post(route('fees.payments.void', $payment), ['reason' => 'Salah input']);

        $reversal = JournalEntry::where('source_type', JournalSource::PaymentVoid)->sole();
        $this->assertSame($original->id, $reversal->reverses_entry_id);
        $this->assertSame('2026-09-12', $reversal->entry_date->toDateString());
        $this->assertSame([['1-1100', '0.00', '350000.00'], ['1-1300', '350000.00', '0.00']], $this->lines($reversal));
        $this->assertSame($originalLines, $this->lines($original->refresh()));
        $this->assertSame('2026-09-10', $original->entry_date->toDateString());
    }

    public function test_void_after_original_period_closed_lands_in_the_current_open_period_ac_14_18(): void
    {
        $bill = $this->bill('2026-09', '2026-09-01');
        $payment = $this->pay($bill);
        AccountingPeriod::where('period', '2026-09')->update(['status' => 'closed']);
        Date::setTestNow('2026-10-05 03:00:00');

        $this->actingAs($this->finance)->post(route('fees.payments.void', $payment), ['reason' => 'Salah input'])->assertRedirect();

        $reversal = JournalEntry::where('source_type', JournalSource::PaymentVoid)->sole();
        $this->assertSame('2026-10-05', $reversal->entry_date->toDateString());
        $this->assertSame('2026-09-10', JournalEntry::findOrFail($reversal->reverses_entry_id)->entry_date->toDateString());
    }

    public function test_void_of_prepaid_payment_recognized_since_reclassifies_receivable_and_prepaid(): void
    {
        $bill = $this->bill('2026-10', null);
        $payment = $this->pay($bill);
        $this->assertSame('-350000.00', $this->balance('2-1100', $bill));

        Date::setTestNow('2026-10-02 03:00:00');
        app(BillService::class)->recognize($bill);
        $this->assertSame('0.00', $this->balance('2-1100', $bill));
        $this->assertSame('0.00', $this->balance('1-1300', $bill));

        Date::setTestNow('2026-10-05 03:00:00');
        $this->actingAs($this->finance)->post(route('fees.payments.void', $payment), ['reason' => 'Salah input'])->assertRedirect();

        $this->assertSame([['1-1100', '0.00', '350000.00'], ['2-1100', '350000.00', '0.00']], $this->lines(JournalEntry::where('source_type', JournalSource::PaymentVoid)->orderBy('id')->firstOrFail()));
        $this->assertSame([['1-1300', '350000.00', '0.00'], ['2-1100', '0.00', '350000.00']], $this->lines(JournalEntry::where('source_type', JournalSource::PaymentVoid)->orderByDesc('id')->firstOrFail()));
        $this->assertSame('350000.00', $this->balance('1-1300', $bill));
        $this->assertSame('0.00', $this->balance('2-1100', $bill));
        $this->assertSame(BillStatus::Unpaid, $bill->refresh()->status);
    }

    public function test_void_of_prepaid_payment_not_yet_recognized_needs_no_reclassification(): void
    {
        $bill = $this->bill('2026-10', null);
        $payment = $this->pay($bill);

        $this->actingAs($this->finance)->post(route('fees.payments.void', $payment), ['reason' => 'Salah input'])->assertRedirect();

        $this->assertSame(1, JournalEntry::where('source_type', JournalSource::PaymentVoid)->count());
        $this->assertSame('0.00', $this->balance('2-1100', $bill));
        $this->assertSame('0.00', $this->balance('1-1300', $bill));
    }

    public function test_only_verified_payments_can_be_voided(): void
    {
        $pending = Payment::factory()->create();
        $voided = Payment::factory()->void()->create();

        foreach ([$pending, $voided] as $payment) {
            $this->actingAs($this->finance)->postJson(route('fees.payments.void', $payment), ['reason' => 'Salah input'])->assertUnprocessable();
        }

        $this->assertSame(PaymentStatus::Pending, $pending->refresh()->status);
        $this->assertSame(0, AuditLog::where('action', 'voided')->count());
    }

    public function test_payment_on_a_written_off_bill_cannot_be_voided(): void
    {
        $bill = $this->bill('2026-09', '2026-09-01');
        $payment = app(PaymentService::class)->createCash([$bill->id => '100000.00'], [$bill->student_id], $this->accountId('1-1100'), $this->finance);
        app(BillService::class)->writeOff($bill->refresh(), 'Tidak tertagih');
        $receivable = $this->balance('1-1300', $bill);

        $this->actingAs($this->finance)->postJson(route('fees.payments.void', $payment), ['reason' => 'Salah input'])->assertUnprocessable();

        $this->assertSame(PaymentStatus::Verified, $payment->refresh()->status);
        $this->assertSame($receivable, $this->balance('1-1300', $bill));
        $this->assertSame(BillStatus::WrittenOff, $bill->refresh()->status);
    }

    public function test_only_admin_and_finance_may_void_ac_14_12(): void
    {
        $payment = $this->pay($this->bill('2026-09', '2026-09-01'));

        foreach ([User::factory()->principal(), User::factory()->teacher(), User::factory()->parent()] as $factory) {
            $this->actingAs($factory->create())->post(route('fees.payments.void', $payment), ['reason' => 'Salah input'])->assertForbidden();
        }

        $this->assertSame(PaymentStatus::Verified, $payment->refresh()->status);
        $this->actingAs(User::factory()->admin()->create())->post(route('fees.payments.void', $payment), ['reason' => 'Salah input'])->assertRedirect();
        $this->assertSame(PaymentStatus::Void, $payment->refresh()->status);
    }

    public function test_receipt_still_renders_after_void(): void
    {
        $payment = $this->pay($this->bill('2026-09', '2026-09-01'));
        $this->actingAs($this->finance)->post(route('fees.payments.void', $payment), ['reason' => 'Salah input']);

        $this->actingAs($this->finance)->get(route('fees.payments.receipt', $payment))->assertOk();
    }
}

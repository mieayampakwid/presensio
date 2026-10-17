<?php

namespace Tests\Unit\Services\Finance;

use App\Enums\BillStatus;
use App\Enums\JournalSource;
use App\Enums\PaymentStatus;
use App\Models\Account;
use App\Models\Bill;
use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Student;
use App\Models\User;
use App\Services\Finance\BillService;
use App\Services\Finance\Exceptions\PaymentOperationException;
use App\Services\Finance\PaymentService;
use App\Services\SchoolSettings;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $service;

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

        $this->service = app(PaymentService::class);
        $this->user = User::factory()->create();
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function bill(array $attributes = []): Bill
    {
        return Bill::factory()->recognized('2026-07-01')->create([
            'fee_type_id' => $this->feeType->id,
            'period_key' => '2026-07',
            'amount' => '350000.00',
            'due_date' => '2026-07-10',
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function pending(Bill $bill, array $attributes = []): Payment
    {
        return $this->service->submitTransfer('350000.00', [$bill->id => '350000.00'], [$bill->student_id], $this->accountId('1-1200'), '2026-07-09', $this->user, 'BCA', null, $attributes['proof_hash'] ?? null);
    }

    /**
     * Assert the entry's lines as [account code, debit, credit] and that it balances.
     *
     * @param  list<array{string, string, string}>  $expected
     */
    private function assertLines(JournalEntry $entry, array $expected): void
    {
        $actual = $entry->lines()->with('account')->orderBy('id')->get()
            ->map(fn ($line) => [$line->account->code, (string) $line->debit, (string) $line->credit])
            ->all();

        $this->assertSame($expected, $actual);
        $this->assertSame((string) $entry->lines->sum('debit'), (string) $entry->lines->sum('credit'));
    }

    private function paymentEntry(Payment $payment): JournalEntry
    {
        return JournalEntry::where('source_type', JournalSource::Payment)->where('source_id', $payment->id)->with('lines')->firstOrFail();
    }

    public function test_cash_for_two_siblings_is_one_verified_payment_with_one_receipt_ac_14_04(): void
    {
        $first = $this->bill();
        $second = $this->bill();

        $payment = $this->service->createCash(
            [$first->id => '350000.00', $second->id => '350000.00'],
            [$first->student_id, $second->student_id],
            $this->accountId('1-1100'),
            $this->user,
        );

        $this->assertSame(PaymentStatus::Verified, $payment->status);
        $this->assertSame('700000.00', $payment->amount);
        $this->assertSame('KWT-202607-00001', $payment->receipt_number);
        $this->assertSame($this->user->id, $payment->verified_by_user_id);
        $this->assertSame(2, $payment->allocations()->count());
        $this->assertSame(BillStatus::Paid, $first->fresh()->status);
        $this->assertSame(BillStatus::Paid, $second->fresh()->status);
        $this->assertSame('350000.00', $first->fresh()->paid_amount);

        $this->assertLines($this->paymentEntry($payment), [
            ['1-1100', '700000.00', '0.00'],
            ['1-1300', '0.00', '350000.00'],
            ['1-1300', '0.00', '350000.00'],
        ]);
        $this->assertSame([$first->student_id, $second->student_id], $this->paymentEntry($payment)->lines->skip(1)->pluck('student_id')->all());
        $this->assertSame([$first->id, $second->id], $this->paymentEntry($payment)->lines->skip(1)->pluck('bill_id')->all());
    }

    public function test_invalid_allocations_return_422_and_leave_no_trace_ac_14_05(): void
    {
        $bill = $this->bill();
        $other = Student::factory()->create();

        foreach ([
            'over remaining' => [[$bill->id => '350000.01'], [$bill->student_id]],
            'zero allocation' => [[$bill->id => '0.00'], [$bill->student_id]],
            'wrong student' => [[$bill->id => '350000.00'], [$other->id]],
        ] as $label => [$allocations, $students]) {
            try {
                $this->service->createCash($allocations, $students, $this->accountId('1-1100'), $this->user);
                $this->fail("{$label} was accepted");
            } catch (PaymentOperationException) {
                $this->assertSame(0, Payment::count(), $label);
            }
        }

        $this->assertSame(0, JournalEntry::where('source_type', JournalSource::Payment)->count());
    }

    public function test_transfer_amount_must_equal_allocation_total_ac_14_05(): void
    {
        $bill = $this->bill();

        $this->expectException(PaymentOperationException::class);
        $this->service->submitTransfer('300000.00', [$bill->id => '350000.00'], [$bill->student_id], $this->accountId('1-1200'), '2026-07-09', $this->user);
    }

    public function test_closed_bills_cannot_be_paid(): void
    {
        $paid = $this->bill(['paid_amount' => '350000.00', 'status' => BillStatus::Paid]);
        PaymentAllocation::factory()->for(Payment::factory()->verified(), 'payment')->for($paid)->create(['amount' => '350000.00']);
        $cancelled = $this->bill(['cancelled_at' => now(), 'status' => BillStatus::Cancelled]);

        foreach ([$paid, $cancelled] as $bill) {
            try {
                $this->service->createCash([$bill->id => '1000.00'], [$bill->student_id], $this->accountId('1-1100'), $this->user);
                $this->fail('closed bill accepted');
            } catch (PaymentOperationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_pending_transfer_posts_nothing_and_bill_stays_unpaid_ac_14_06(): void
    {
        $bill = $this->bill();
        $payment = $this->pending($bill);

        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertNull($payment->receipt_number);
        $this->assertSame(0, JournalEntry::where('source_type', JournalSource::Payment)->count());
        $this->assertSame(BillStatus::Unpaid, $bill->fresh()->status);
    }

    public function test_verify_assigns_receipt_posts_and_recomputes(): void
    {
        $bill = $this->bill();
        $payment = $this->pending($bill);

        $this->service->verify($payment, null, $this->accountId('1-1200'), $this->user);

        $payment->refresh();
        $this->assertSame(PaymentStatus::Verified, $payment->status);
        $this->assertSame('KWT-202607-00001', $payment->receipt_number);
        $this->assertSame(BillStatus::Paid, $bill->fresh()->status);
        $this->assertLines($this->paymentEntry($payment), [
            ['1-1200', '350000.00', '0.00'],
            ['1-1300', '0.00', '350000.00'],
        ]);
    }

    public function test_verify_can_reallocate_within_the_guardians_children(): void
    {
        $guardian = Guardian::factory()->create(['user_id' => $this->user->id]);
        $first = $this->bill();
        $second = $this->bill();
        $guardian->students()->attach([$first->student_id, $second->student_id]);
        $payment = $this->service->submitTransfer('350000.00', [$first->id => '350000.00'], [$first->student_id, $second->student_id], $this->accountId('1-1200'), '2026-07-09', $this->user);

        $this->service->verify($payment, [$first->id => '100000.00', $second->id => '250000.00'], $this->accountId('1-1200'), User::factory()->create());

        $this->assertSame(['100000.00', '250000.00'], $payment->allocations()->orderBy('bill_id')->pluck('amount')->all());
        $this->assertSame(BillStatus::PartiallyPaid, $first->fresh()->status);
        $this->assertSame('250000.00', $second->fresh()->paid_amount);
    }

    public function test_verify_rejects_reallocation_outside_allowed_students_and_bad_totals(): void
    {
        $bill = $this->bill();
        $stranger = $this->bill();
        $payment = $this->pending($bill);

        foreach ([[$stranger->id => '350000.00'], [$bill->id => '300000.00']] as $reallocation) {
            try {
                $this->service->verify($payment, $reallocation, $this->accountId('1-1200'), $this->user);
                $this->fail('reallocation accepted');
            } catch (PaymentOperationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertSame([$bill->id], $payment->allocations()->pluck('bill_id')->all());
    }

    public function test_reject_leaves_bill_partially_paid_ac_14_07(): void
    {
        $bill = $this->bill();
        $earlier = Payment::factory()->verified()->create(['amount' => '200000.00']);
        PaymentAllocation::factory()->for($earlier, 'payment')->for($bill)->create(['amount' => '200000.00']);
        app(BillService::class)->recompute($bill);
        $payment = $this->service->submitTransfer('150000.00', [$bill->id => '150000.00'], [$bill->student_id], $this->accountId('1-1200'), '2026-07-09', $this->user);

        $this->service->reject($payment, 'Bukti tidak sesuai', $this->user);

        $payment->refresh();
        $this->assertSame(PaymentStatus::Rejected, $payment->status);
        $this->assertSame('Bukti tidak sesuai', $payment->reject_reason);
        $this->assertSame(BillStatus::PartiallyPaid, $bill->fresh()->status);
        $this->assertSame('200000.00', $bill->fresh()->paid_amount);
        $this->assertSame(0, JournalEntry::where('source_type', JournalSource::Payment)->count());
    }

    public function test_only_pending_payments_can_be_verified_or_rejected_and_reject_needs_a_reason(): void
    {
        $bill = $this->bill();
        $payment = $this->pending($bill);

        try {
            $this->service->reject($payment, ' ', $this->user);
            $this->fail('blank reason accepted');
        } catch (PaymentOperationException) {
            $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        }

        $this->service->reject($payment, 'Salah', $this->user);

        foreach ([
            fn () => $this->service->verify($payment, null, $this->accountId('1-1200'), $this->user),
            fn () => $this->service->reject($payment, 'Lagi', $this->user),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('rejected payment was processed again');
            } catch (PaymentOperationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_duplicate_proof_warning_ignores_rejected_and_self_ac_14_10(): void
    {
        $hash = str_repeat('a', 64);
        $first = $this->pending($this->bill(), ['proof_hash' => $hash]);
        $second = $this->pending($this->bill(), ['proof_hash' => $hash]);
        $rejected = $this->pending($this->bill(), ['proof_hash' => $hash]);
        $this->service->reject($rejected, 'Duplikat', $this->user);
        $this->pending($this->bill(), ['proof_hash' => str_repeat('b', 64)]);

        $this->assertSame([$first->id], $this->service->duplicateProofWarnings($second)->pluck('id')->all());
        $this->assertCount(0, $this->service->duplicateProofWarnings($this->pending($this->bill())));
    }

    public function test_prepaid_payment_then_recognition_leaves_receivable_untouched_ac_14_15(): void
    {
        Date::setTestNow('2026-09-20 03:00:00');
        $bill = $this->bill(['period_key' => '2026-10', 'recognized_at' => null, 'due_date' => '2026-10-10']);

        $payment = $this->service->createCash([$bill->id => '350000.00'], [$bill->student_id], $this->accountId('1-1100'), $this->user);

        $this->assertLines($this->paymentEntry($payment), [
            ['1-1100', '350000.00', '0.00'],
            ['2-1100', '0.00', '350000.00'],
        ]);
        $this->assertSame(BillStatus::Paid, $bill->fresh()->status);

        Date::setTestNow('2026-10-01 03:00:00');
        app(BillService::class)->recognize($bill);

        $recognition = JournalEntry::where('source_type', JournalSource::BillRecognition)->where('source_id', $bill->id)->with('lines')->firstOrFail();
        $this->assertLines($recognition, [
            ['2-1100', '350000.00', '0.00'],
            ['4-1100', '0.00', '350000.00'],
        ]);
        $receivable = $this->accountId('1-1300');
        $this->assertSame(0, $recognition->lines->where('account_id', $receivable)->count());
        $this->assertSame(0, $this->paymentEntry($payment)->lines->where('account_id', $receivable)->count());
    }
}

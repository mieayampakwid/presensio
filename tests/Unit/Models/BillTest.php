<?php

namespace Tests\Unit\Models;

use App\Enums\BillStatus;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class BillTest extends TestCase
{
    use RefreshDatabase;

    public function test_net_and_remaining_amounts_account_for_discount_and_payments(): void
    {
        $bill = Bill::factory()->partiallyPaid('100000.00')->create([
            'amount' => '350000.00',
            'discount_amount' => '175000.00',
        ]);

        $this->assertSame('175000.00', $bill->netAmount());
        $this->assertSame('75000.00', $bill->remainingAmount());
        $this->assertSame(BillStatus::PartiallyPaid, $bill->fresh()->status);
    }

    public function test_remaining_amount_never_goes_negative(): void
    {
        $bill = Bill::factory()->create([
            'amount' => '100000.00',
            'paid_amount' => '120000.00',
        ]);

        $this->assertSame('0.00', $bill->remainingAmount());
    }

    public function test_recognition_is_derived_from_recognized_at(): void
    {
        $this->assertFalse(Bill::factory()->create()->isRecognized());
        $this->assertTrue(Bill::factory()->recognized('2026-07-01')->create()->isRecognized());
    }

    public function test_student_fee_type_period_must_be_unique(): void
    {
        $bill = Bill::factory()->create(['period_key' => '2026-09']);

        $this->expectException(QueryException::class);

        Bill::factory()->create([
            'student_id' => $bill->student_id,
            'fee_type_id' => $bill->fee_type_id,
            'period_key' => '2026-09',
        ]);
    }

    public function test_pending_payment_and_its_allocations_stay_editable(): void
    {
        $payment = Payment::factory()->create();
        $allocation = PaymentAllocation::factory()->create(['payment_id' => $payment->id]);

        $payment->update(['amount' => '200000.00']);
        $allocation->update(['amount' => '200000.00']);
        $allocation->delete();

        $this->assertSame('200000.00', $payment->fresh()->amount);
        $this->assertDatabaseMissing('payment_allocations', ['id' => $allocation->id]);
    }

    public function test_verified_payment_rejects_edits_and_deletion(): void
    {
        $payment = Payment::factory()->verified()->create();

        try {
            $payment->update(['amount' => '1.00']);
            $this->fail('Verified payments must not be editable.');
        } catch (LogicException) {
        }

        try {
            $payment->delete();
            $this->fail('Verified payments must not be deletable.');
        } catch (LogicException) {
        }

        $this->assertSame('350000.00', $payment->fresh()->amount);
    }

    public function test_verified_payment_accepts_only_the_void_stamp(): void
    {
        $payment = Payment::factory()->verified()->create();
        $user = User::factory()->create();

        $payment->update([
            'status' => 'void',
            'void_reason' => 'Salah input',
            'voided_at' => now(),
            'voided_by_user_id' => $user->id,
        ]);

        $this->assertSame('void', $payment->fresh()->status->value);
        $this->assertSame('Salah input', $payment->fresh()->void_reason);
    }

    public function test_void_stamp_cannot_smuggle_other_field_changes(): void
    {
        $payment = Payment::factory()->verified()->create();

        $this->expectException(LogicException::class);

        $payment->update(['status' => 'void', 'amount' => '1.00']);
    }

    public function test_voided_payment_is_frozen(): void
    {
        $payment = Payment::factory()->void()->create();

        $this->expectException(LogicException::class);

        $payment->update(['notes' => 'edit']);
    }

    public function test_allocations_of_a_verified_payment_cannot_change(): void
    {
        $payment = Payment::factory()->create();
        $allocation = PaymentAllocation::factory()->create(['payment_id' => $payment->id]);
        $payment->update(['status' => 'verified', 'verified_at' => now()]);

        try {
            $allocation->update(['amount' => '1.00']);
            $this->fail('Verified allocations must not be editable.');
        } catch (LogicException) {
        }

        try {
            $allocation->delete();
            $this->fail('Verified allocations must not be deletable.');
        } catch (LogicException) {
        }

        $this->assertSame('350000.00', $allocation->fresh()->amount);
    }
}

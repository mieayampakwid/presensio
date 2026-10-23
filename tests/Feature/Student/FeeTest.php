<?php

namespace Tests\Feature\Student;

use App\Models\Bill;
use App\Models\FeeType;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FeeTest extends TestCase
{
    use RefreshDatabase;

    private function billFor(Student $student, string $title): Bill
    {
        return Bill::factory()->recognized('2026-09-01')->create([
            'student_id' => $student->id,
            'fee_type_id' => FeeType::factory(),
            'title' => $title,
            'due_date' => '2026-09-10',
        ]);
    }

    public function test_student_sees_only_own_bills_read_only(): void
    {
        $user = User::factory()->student()->create();
        $student = Student::factory()->create(['user_id' => $user->id]);
        $this->billFor($student, 'SPP Milik Saya');
        $this->billFor(Student::factory()->create(), 'SPP Orang Lain');

        $this->actingAs($user)
            ->get(route('student.fees.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('parent/fees')
                ->where('canUpload', false)
                ->has('portal.bills', 1)
                ->where('portal.bills.0.title', 'SPP Milik Saya'));
    }

    public function test_non_student_roles_cannot_open_student_fees(): void
    {
        $this->actingAs(User::factory()->parent()->create())
            ->get(route('student.fees.index'))
            ->assertForbidden();
        $this->actingAs(User::factory()->teacher()->create())
            ->get(route('student.fees.index'))
            ->assertForbidden();
    }

    public function test_student_cannot_upload_transfer_proof(): void
    {
        $this->actingAs(User::factory()->student()->create())
            ->post(route('parent.fees.transfers.store'))
            ->assertForbidden();
    }

    public function test_student_payment_rows_never_grant_cancellation(): void
    {
        $user = User::factory()->student()->create();
        $student = Student::factory()->create(['user_id' => $user->id]);
        $bill = $this->billFor($student, 'SPP');
        $payment = Payment::factory()->create();
        $payment->allocations()->create(['bill_id' => $bill->id, 'amount' => '350000.00']);
        $this->actingAs($user)->get(route('student.fees.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('portal.payments.0.can_cancel', false)->missing('pendingTransfers'));
    }
}

<?php

namespace Tests\Feature\Parent;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Guardian;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CancelTransferTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Bill, Payment} */
    private function transfer(PaymentStatus $status = PaymentStatus::Pending): array
    {
        $parent = User::factory()->parent()->create();
        $guardian = Guardian::factory()->create(['user_id' => $parent->id]);
        $student = Student::factory()->create();
        $guardian->students()->attach($student->id, ['relationship_type' => 'father']);
        $bill = Bill::factory()->create(['student_id' => $student->id]);
        $factory = match ($status) {
            PaymentStatus::Verified => Payment::factory()->verified(),
            PaymentStatus::Rejected => Payment::factory()->rejected(),
            PaymentStatus::Void => Payment::factory()->void(),
            default => Payment::factory(),
        };
        $payment = $factory->create(['submitted_by_user_id' => $parent->id]);
        $payment->allocations()->create(['bill_id' => $bill->id, 'amount' => '350000.00']);

        return [$parent, $bill, $payment];
    }

    public function test_linked_guardian_cancels_with_one_audit_and_keeps_the_proof(): void
    {
        Storage::fake('local');
        [$parent, $bill, $payment] = $this->transfer();
        $payment->update(['proof_path' => 'payment-proofs/original.pdf']);
        Storage::disk('local')->put($payment->proof_path, 'proof');
        $beforeBill = [$bill->status, $bill->paid_amount];

        $this->actingAs($parent)->post(route('parent.fees.transfers.cancel', $payment))
            ->assertRedirect(route('parent.fees.index', ['transfers_page' => 1]))
            ->assertInertiaFlash('toast.type', 'success');

        $this->assertSame(PaymentStatus::Rejected, $payment->refresh()->status);
        $this->assertSame('Dibatalkan oleh wali', $payment->reject_reason);
        $this->assertNull($payment->receipt_number);
        $this->assertSame($beforeBill, [$bill->refresh()->status, $bill->paid_amount]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $payment->getMorphClass(), 'auditable_id' => $payment->id,
            'action' => 'cancelled_by_guardian', 'user_id' => $parent->id,
        ]);
        $this->assertSame(1, AuditLog::where('action', 'cancelled_by_guardian')->count());
        $this->assertSame(0, JournalEntry::count());
        $this->assertSame(0, DB::table('notifications')->count());
        $this->assertSame(0, DB::table('notification_deliveries')->count());
        Storage::disk('local')->assertExists($payment->proof_path);

        $this->postJson(route('parent.fees.transfers.cancel', $payment))->assertUnprocessable();
        $this->assertSame(1, AuditLog::where('action', 'cancelled_by_guardian')->count());
    }

    public function test_another_guardian_linked_to_all_students_can_cancel(): void
    {
        [$submitter, $bill, $payment] = $this->transfer();
        $other = User::factory()->parent()->create();
        Guardian::factory()->create(['user_id' => $other->id])->students()->attach($bill->student_id, ['relationship_type' => 'mother']);

        $this->actingAs($other)->post(route('parent.fees.transfers.cancel', $payment))->assertRedirect();

        $this->assertSame(PaymentStatus::Rejected, $payment->refresh()->status);
        $this->assertSame($submitter->id, $payment->submitted_by_user_id);
        $this->assertSame($other->id, AuditLog::where('action', 'cancelled_by_guardian')->sole()->user_id);
    }

    /** @return array<string, array{UserRole, bool}> */
    public static function additionalRoles(): array
    {
        $cases = [];
        foreach ([UserRole::Admin, UserRole::Teacher, UserRole::Finance, UserRole::Principal] as $role) {
            $cases[$role->value.' active'] = [$role, false];
            $cases[$role->value.' parent active'] = [$role, true];
        }

        return $cases;
    }

    #[DataProvider('additionalRoles')]
    public function test_parent_capability_is_preserved_with_an_additional_role(UserRole $role, bool $parentActive): void
    {
        [$parent, $bill, $payment] = $this->transfer();
        $parent->roleGrants()->create(['role' => $role, 'created_at' => now()]);
        $parent->unsetRelation('roleGrants');
        $this->actingAs($parent)->withSession(['active_role' => ($parentActive ? UserRole::Parent : $role)->value])
            ->post(route('parent.fees.transfers.cancel', $payment))->assertRedirect();
        $this->assertSame(PaymentStatus::Rejected, $payment->refresh()->status);
        $this->assertSame($parent->id, AuditLog::where('action', 'cancelled_by_guardian')->sole()->user_id);
    }

    /** @return array<string, array{UserRole}> */
    public static function nonParentRoles(): array
    {
        return array_combine(
            array_map(fn (UserRole $role) => $role->value, array_filter(UserRole::cases(), fn (UserRole $role) => $role !== UserRole::Parent)),
            array_map(fn (UserRole $role) => [$role], array_filter(UserRole::cases(), fn (UserRole $role) => $role !== UserRole::Parent)),
        );
    }

    #[DataProvider('nonParentRoles')]
    public function test_a_non_parent_role_cannot_cancel_even_with_a_linked_profile(UserRole $role): void
    {
        [$parent, $bill, $payment] = $this->transfer();
        $parent->roleGrants()->update(['role' => $role]);
        $parent->unsetRelation('roleGrants');
        $before = $payment->refresh()->getAttributes();
        $this->actingAs($parent)->postJson(route('parent.fees.transfers.cancel', $payment))->assertForbidden();
        $this->assertSame($before, $payment->refresh()->getAttributes());
        $this->assertSame(0, AuditLog::count());
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_missing_guardian_cash_and_empty_allocations_are_forbidden(): void
    {
        [$parent, $bill, $payment] = $this->transfer();
        $this->actingAs(User::factory()->parent()->create())
            ->postJson(route('parent.fees.transfers.cancel', $payment))->assertForbidden();
        $payment->update(['payment_method' => PaymentMethod::Cash]);
        $this->actingAs($parent)->postJson(route('parent.fees.transfers.cancel', $payment))->assertForbidden();
        $payment->update(['payment_method' => PaymentMethod::BankTransfer]);
        $payment->allocations()->delete();
        $this->postJson(route('parent.fees.transfers.cancel', $payment))->assertForbidden();
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertSame(0, AuditLog::count());
        $this->assertSame(0, JournalEntry::count());
    }

    /** @return array<string, array{PaymentStatus}> */
    public static function terminalStatuses(): array
    {
        return ['verified' => [PaymentStatus::Verified], 'rejected' => [PaymentStatus::Rejected], 'void' => [PaymentStatus::Void]];
    }

    #[DataProvider('terminalStatuses')]
    public function test_terminal_status_is_rejected_only_after_authorization(PaymentStatus $status): void
    {
        [$parent, $bill, $payment] = $this->transfer($status);
        $before = $payment->refresh()->getAttributes();
        $this->actingAs($parent)->postJson(route('parent.fees.transfers.cancel', $payment))->assertUnprocessable();
        $hiddenBill = Bill::factory()->create();
        $payment->allocations()->create(['bill_id' => $hiddenBill->id, 'amount' => '1.00']);
        $this->postJson(route('parent.fees.transfers.cancel', $payment))->assertForbidden();
        $this->assertSame($before, $payment->refresh()->getAttributes());
        $this->assertSame(0, AuditLog::count());
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_a_parent_linked_to_only_one_allocated_student_cannot_cancel_pending_transfer(): void
    {
        [$parent, $bill, $payment] = $this->transfer();
        $hiddenBill = Bill::factory()->create();
        $payment->allocations()->create(['bill_id' => $hiddenBill->id, 'amount' => '1.00']);
        $this->actingAs($parent)->postJson(route('parent.fees.transfers.cancel', $payment))->assertForbidden();
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_guests_redirect_to_login_and_missing_payments_return_not_found(): void
    {
        [$parent, $bill, $payment] = $this->transfer();
        $this->post(route('parent.fees.transfers.cancel', $payment))->assertRedirect(route('login'));
        $this->actingAs($parent)->postJson(route('parent.fees.transfers.cancel', 999999))->assertNotFound();
    }
}

<?php

namespace Tests\Feature\Parent;

use App\Enums\BillStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Enrollment;
use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\SchoolSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FeePortalTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;

    private Student $child;

    private Student $stranger;

    private Account $transferAccount;

    protected function setUp(): void
    {
        parent::setUp();

        Date::setTestNow('2026-09-12 03:00:00');
        Storage::fake('local');

        $this->transferAccount = Account::factory()->cash()->create();
        app(SchoolSettings::class)->row()->update([
            'default_transfer_account_id' => $this->transferAccount->id,
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_holder' => 'Yayasan Sekolah',
        ]);

        $this->parent = User::factory()->parent()->create();
        $guardian = Guardian::factory()->create(['user_id' => $this->parent->id]);
        $this->child = Student::factory()->create(['full_name' => 'Budi Santoso']);
        $guardian->students()->attach($this->child->id, ['relationship_type' => 'father']);
        Enrollment::factory()->create([
            'student_id' => $this->child->id,
            'class_id' => SchoolClass::factory()->create(['name' => '5A'])->id,
            'started_on' => '2026-07-01',
            'ended_on' => null,
        ]);
        $this->stranger = Student::factory()->create();
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    private function billFor(Student $student, string $amount = '350000.00'): Bill
    {
        return Bill::factory()->recognized('2026-09-01')->create([
            'student_id' => $student->id,
            'fee_type_id' => FeeType::factory(),
            'period_key' => '2026-09',
            'title' => 'SPP September 2026',
            'amount' => $amount,
            'due_date' => '2026-09-10',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Bill $bill, string $amount = '350000', array $overrides = []): array
    {
        return [
            'amount' => $amount,
            'allocations' => [['bill_id' => $bill->id, 'amount' => $amount]],
            'payment_date' => '2026-09-11',
            'origin_bank' => 'Mandiri',
            'proof' => UploadedFile::fake()->image('bukti.jpg'),
            ...$overrides,
        ];
    }

    public function test_index_scopes_bills_totals_and_receipt_links_to_linked_children(): void
    {
        $open = $this->billFor($this->child);
        $paid = Bill::factory()->paid()->create(['student_id' => $this->child->id, 'due_date' => '2026-08-10']);
        $this->billFor($this->stranger);
        $pending = Payment::factory()->create(['amount' => '100000.00']);
        PaymentAllocation::create(['payment_id' => $pending->id, 'bill_id' => $open->id, 'amount' => '100000.00']);
        $verified = Payment::factory()->verified()->create(['amount' => '350000.00']);
        PaymentAllocation::create(['payment_id' => $verified->id, 'bill_id' => $paid->id, 'amount' => '350000.00']);
        $foreign = Payment::factory()->verified()->create();
        PaymentAllocation::create(['payment_id' => $foreign->id, 'bill_id' => Bill::where('student_id', $this->stranger->id)->value('id'), 'amount' => '350000.00']);

        $this->actingAs($this->parent)
            ->get(route('parent.fees.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('parent/fees')
                ->where('portal.summary', ['unpaid' => '350000.00', 'pending' => '100000.00', 'paid' => '350000.00'])
                ->has('portal.bills', 2)
                ->where('portal.bills.1.id', $open->id)
                ->where('portal.bills.1.class_name', '5A')
                ->where('portal.bills.1.has_pending', true)
                ->where('portal.bills.0.has_pending', false)
                ->has('portal.payments', 2)
                ->where('portal.payments.0.can_view_receipt', true)
                ->where('portal.payments.1.can_view_receipt', false)
                ->where('schoolBank.bank_name', 'BCA')
                ->where('transferAccountConfigured', true)
                ->has('students', 1));
    }

    public function test_store_creates_pending_transfer_without_touching_the_bill_or_ledger(): void
    {
        $bill = $this->billFor($this->child);
        $file = UploadedFile::fake()->image('bukti.jpg');
        $payload = $this->payload($bill, overrides: ['proof' => $file, 'notes' => 'Transfer SPP']);

        $this->actingAs($this->parent)
            ->post(route('parent.fees.transfers.store'), $payload)
            ->assertRedirect(route('parent.fees.index'))
            ->assertInertiaFlash('toast.type', 'success');

        $payment = Payment::sole();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(PaymentMethod::BankTransfer, $payment->payment_method);
        $this->assertSame('350000.00', $payment->amount);
        $this->assertSame($this->transferAccount->id, $payment->deposit_account_id);
        $this->assertSame($this->parent->id, $payment->submitted_by_user_id);
        $this->assertSame('Mandiri', $payment->origin_bank);
        $this->assertSame('2026-09-11', $payment->payment_date->toDateString());
        $this->assertNull($payment->receipt_number);
        $this->assertSame(hash_file('sha256', $file->getRealPath()), $payment->proof_hash);
        $this->assertStringStartsWith('payment-proofs/', $payment->proof_path);
        Storage::disk('local')->assertExists($payment->proof_path);
        $this->assertSame($bill->id, $payment->allocations->sole()->bill_id);
        $this->assertSame(BillStatus::Unpaid, $bill->refresh()->status);
        $this->assertSame('0.00', $bill->paid_amount);
        $this->assertSame(0, JournalEntry::where('source_id', $payment->id)->count());
    }

    public function test_unlinked_students_bill_is_forbidden_and_nothing_is_stored(): void
    {
        $bill = $this->billFor($this->stranger);

        $this->actingAs($this->parent)
            ->post(route('parent.fees.transfers.store'), $this->payload($bill))
            ->assertForbidden();

        $this->assertSame(0, Payment::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_overpaying_a_bill_is_rejected_and_the_proof_is_removed(): void
    {
        $bill = $this->billFor($this->child);

        $this->actingAs($this->parent)
            ->post(route('parent.fees.transfers.store'), $this->payload($bill, '400000'))
            ->assertSessionHasErrors('allocations');

        $this->assertSame(0, Payment::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_invalid_input_is_rejected_without_creating_a_payment(): void
    {
        $bill = $this->billFor($this->child);

        $this->actingAs($this->parent)->post(route('parent.fees.transfers.store'), $this->payload($bill, overrides: ['proof' => UploadedFile::fake()->create('bukti.txt', 10, 'text/plain')]))->assertSessionHasErrors('proof');
        $this->actingAs($this->parent)->post(route('parent.fees.transfers.store'), $this->payload($bill, overrides: ['proof' => UploadedFile::fake()->create('bukti.pdf', 6000, 'application/pdf')]))->assertSessionHasErrors('proof');
        $this->actingAs($this->parent)->post(route('parent.fees.transfers.store'), $this->payload($bill, overrides: ['proof' => null]))->assertSessionHasErrors('proof');
        $this->actingAs($this->parent)->post(route('parent.fees.transfers.store'), $this->payload($bill, '300000', ['allocations' => [['bill_id' => $bill->id, 'amount' => '350000']]]))->assertSessionHasErrors('amount');
        $this->actingAs($this->parent)->post(route('parent.fees.transfers.store'), $this->payload($bill, overrides: ['payment_date' => '2026-09-13']))->assertSessionHasErrors('payment_date');

        $this->assertSame(0, Payment::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_store_requires_a_configured_transfer_account(): void
    {
        app(SchoolSettings::class)->row()->update(['default_transfer_account_id' => null]);
        $bill = $this->billFor($this->child);

        $this->actingAs($this->parent)
            ->post(route('parent.fees.transfers.store'), $this->payload($bill))
            ->assertSessionHasErrors(['transfer' => 'Rekening tujuan transfer belum diatur oleh sekolah.']);

        $this->assertSame(0, Payment::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_non_parent_roles_and_parents_without_guardian_are_forbidden(): void
    {
        $bill = $this->billFor($this->child);
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)->get(route('parent.fees.index'))->assertForbidden();
        $this->actingAs($teacher)->post(route('parent.fees.transfers.store'), $this->payload($bill))->assertForbidden();
        $this->actingAs(User::factory()->parent()->create())->get(route('parent.fees.index'))->assertForbidden();
        $this->assertSame(0, Payment::count());
    }

    public function test_admin_without_guardian_sees_an_empty_portal(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('parent.fees.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('portal.bills', 0)->has('students', 0));
    }
}

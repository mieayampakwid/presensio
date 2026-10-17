<?php

namespace Tests\Feature\Fees;

use App\Enums\BillStatus;
use App\Enums\JournalSource;
use App\Enums\PaymentStatus;
use App\Models\AcademicYear;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\Finance\PaymentService;
use App\Services\SchoolSettings;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    use RefreshDatabase;

    private FeeType $spp;

    private User $finance;

    private User $parent;

    private Student $child;

    private Student $sibling;

    protected function setUp(): void
    {
        parent::setUp();

        Date::setTestNow('2026-09-12 03:00:00');
        Storage::fake('local');

        app(SchoolSettings::class)->row();
        $this->seed(ChartOfAccountsSeeder::class);
        app(SchoolSettings::class)->refresh();
        app(SchoolSettings::class)->row()->update(['default_transfer_account_id' => $this->accountId('1-1200')]);

        $this->spp = FeeType::factory()->create([
            'receivable_account_id' => $this->accountId('1-1300'),
            'revenue_account_id' => $this->accountId('4-1100'),
            'discount_account_id' => $this->accountId('4-1900'),
        ]);
        $this->finance = User::factory()->finance()->create();
        $this->parent = User::factory()->parent()->create();
        $guardian = Guardian::factory()->create(['user_id' => $this->parent->id]);
        $this->child = Student::factory()->create(['full_name' => 'Budi Santoso']);
        $this->sibling = Student::factory()->create(['full_name' => 'Sari Santoso']);
        $guardian->students()->attach([$this->child->id => ['relationship_type' => 'father'], $this->sibling->id => ['relationship_type' => 'father']]);
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

    private function billFor(Student $student, string $period = '2026-09'): Bill
    {
        return Bill::factory()->recognized("{$period}-01")->create([
            'student_id' => $student->id,
            'fee_type_id' => $this->spp->id,
            'academic_year_id' => AcademicYear::query()->value('id') ?? AcademicYear::factory()->create()->id,
            'period_key' => $period,
            'title' => "SPP {$period}",
            'amount' => '350000.00',
            'due_date' => "{$period}-10",
        ]);
    }

    private function transfer(Bill $bill, string $amount = '350000.00', ?string $hash = null, bool $withProof = false): Payment
    {
        $path = null;

        if ($withProof) {
            $path = 'payment-proofs/proof.jpg';
            Storage::disk('local')->put($path, 'img');
        }

        return app(PaymentService::class)->submitTransfer(
            $amount,
            [$bill->id => $amount],
            [$bill->student_id],
            $this->accountId('1-1200'),
            '2026-09-11',
            $this->parent,
            'Mandiri',
            $path,
            $hash,
        );
    }

    public function test_queue_lists_only_pending_transfers_with_duplicate_warning_ac_14_10(): void
    {
        $bill = $this->billFor($this->child);
        $pending = $this->transfer($bill, hash: 'abc');
        $other = $this->transfer($this->billFor($this->child, '2026-10'), hash: 'abc');
        $rejected = $this->transfer($this->billFor($this->sibling), hash: 'zzz');
        $rejected->update(['status' => PaymentStatus::Rejected]);
        Payment::factory()->verified()->create(['proof_hash' => 'zzz']);
        Payment::factory()->cash()->create();

        $this->actingAs($this->finance)->get(route('fees.verifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('fees/verifications')
                ->has('payments', 2)
                ->where('payments.0.id', $pending->id)
                ->where('payments.0.submitted_by', $this->parent->username)
                ->where('payments.0.duplicates.0.id', $other->id)
                ->has('payments.0.candidate_bills', 3)
                ->where('canManage', true));
    }

    public function test_rejected_payment_with_same_hash_is_not_a_duplicate_warning(): void
    {
        $pending = $this->transfer($this->billFor($this->child), hash: 'abc');
        Payment::factory()->rejected()->create(['proof_hash' => 'abc']);

        $this->actingAs($this->finance)->get(route('fees.verifications.index'))
            ->assertInertia(fn (Assert $page) => $page->where('payments.0.id', $pending->id)->where('payments.0.duplicates', []));
    }

    public function test_verify_posts_journal_updates_bill_and_audits(): void
    {
        $bill = $this->billFor($this->child);
        $payment = $this->transfer($bill);
        $account = Account::factory()->cash()->create();

        $this->actingAs($this->finance)->post(route('fees.verifications.verify', $payment), ['deposit_account_id' => $account->id])
            ->assertRedirect(route('fees.verifications.index'));

        $payment->refresh();
        $this->assertSame(PaymentStatus::Verified, $payment->status);
        $this->assertMatchesRegularExpression('/^KWT-/', $payment->receipt_number);
        $this->assertSame($account->id, $payment->deposit_account_id);
        $this->assertSame($this->finance->id, $payment->verified_by_user_id);
        $bill->refresh();
        $this->assertSame(BillStatus::Paid, $bill->status);
        $this->assertSame('350000.00', $bill->paid_amount);
        $this->assertSame(1, JournalEntry::where('source_type', JournalSource::Payment)->where('source_id', $payment->id)->count());
        $this->assertSame($payment->id, AuditLog::where('action', 'verified')->sole()->auditable_id);
    }

    public function test_verify_with_reallocation_to_sibling_bill_and_rejects_unbalanced_split(): void
    {
        $own = $this->billFor($this->child);
        $siblingBill = $this->billFor($this->sibling);
        $payment = $this->transfer($own);
        $account = $this->accountId('1-1200');

        $this->actingAs($this->finance)->post(route('fees.verifications.verify', $payment), [
            'deposit_account_id' => $account,
            'allocations' => [['bill_id' => $siblingBill->id, 'amount' => '300000']],
        ])->assertStatus(422);

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertSame(0, JournalEntry::where('source_type', JournalSource::Payment)->count());
        $this->assertSame(0, AuditLog::where('action', 'verified')->count());

        $this->actingAs($this->finance)->post(route('fees.verifications.verify', $payment), [
            'deposit_account_id' => $account,
            'allocations' => [['bill_id' => $siblingBill->id, 'amount' => '350000']],
        ])->assertRedirect();

        $this->assertSame(PaymentStatus::Verified, $payment->refresh()->status);
        $this->assertSame(BillStatus::Paid, $siblingBill->refresh()->status);
        $this->assertSame(BillStatus::Unpaid, $own->refresh()->status);
        $this->assertSame([$siblingBill->id], $payment->allocations()->pluck('bill_id')->all());
    }

    public function test_reject_requires_reason_then_leaves_bill_partially_paid_ac_14_07(): void
    {
        $bill = $this->billFor($this->child);
        app(PaymentService::class)->createCash([$bill->id => '200000.00'], [$this->child->id], $this->accountId('1-1100'), $this->finance);
        $payment = $this->transfer($bill, '150000.00');
        $entries = JournalEntry::count();

        $this->actingAs($this->finance)->postJson(route('fees.verifications.reject', $payment), ['reason' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);

        $this->actingAs($this->finance)->post(route('fees.verifications.reject', $payment), ['reason' => 'Bukti buram'])
            ->assertRedirect(route('fees.verifications.index'));

        $payment->refresh();
        $this->assertSame(PaymentStatus::Rejected, $payment->status);
        $this->assertSame('Bukti buram', $payment->reject_reason);
        $bill->refresh();
        $this->assertSame(BillStatus::PartiallyPaid, $bill->status);
        $this->assertSame('200000.00', $bill->paid_amount);
        $this->assertSame($entries, JournalEntry::count());
        $this->assertSame('Bukti buram', AuditLog::where('action', 'rejected')->sole()->reason);
    }

    public function test_non_pending_payment_cannot_be_verified_or_rejected(): void
    {
        $payment = $this->transfer($this->billFor($this->child));
        $payment->update(['status' => PaymentStatus::Rejected]);

        $this->actingAs($this->finance)->post(route('fees.verifications.verify', $payment), ['deposit_account_id' => $this->accountId('1-1200')])->assertStatus(422);
        $this->actingAs($this->finance)->post(route('fees.verifications.reject', $payment), ['reason' => 'x'])->assertStatus(422);
        $this->assertSame(PaymentStatus::Rejected, $payment->refresh()->status);
    }

    public function test_principal_can_view_queue_and_proof_but_not_act(): void
    {
        $payment = $this->transfer($this->billFor($this->child), withProof: true);
        $principal = User::factory()->principal()->create();

        $this->actingAs($principal)->get(route('fees.verifications.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('canManage', false));
        $this->actingAs($principal)->get(route('fees.verifications.proof', $payment))->assertOk();
        $this->actingAs($principal)->post(route('fees.verifications.verify', $payment), ['deposit_account_id' => $this->accountId('1-1200')])->assertForbidden();
        $this->actingAs($principal)->post(route('fees.verifications.reject', $payment), ['reason' => 'x'])->assertForbidden();
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
    }

    public function test_parent_and_teacher_are_forbidden_on_all_routes(): void
    {
        $payment = $this->transfer($this->billFor($this->child), withProof: true);

        foreach ([$this->parent, User::factory()->teacher()->create()] as $user) {
            $this->actingAs($user)->get(route('fees.verifications.index'))->assertForbidden();
            $this->actingAs($user)->get(route('fees.verifications.proof', $payment))->assertForbidden();
            $this->actingAs($user)->post(route('fees.verifications.verify', $payment))->assertForbidden();
            $this->actingAs($user)->post(route('fees.verifications.reject', $payment))->assertForbidden();
        }
    }

    public function test_proof_streams_for_finance_and_is_404_when_file_missing(): void
    {
        $withFile = $this->transfer($this->billFor($this->child), withProof: true);
        $missing = $this->transfer($this->billFor($this->sibling));
        $missing->update(['proof_path' => 'payment-proofs/gone.jpg']);

        $this->actingAs($this->finance)->get(route('fees.verifications.proof', $withFile))->assertOk();
        $this->actingAs($this->finance)->get(route('fees.verifications.proof', $missing))->assertNotFound();
    }
}

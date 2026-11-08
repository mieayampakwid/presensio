<?php

namespace Tests\Feature\Fees;

use App\Enums\BillStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Fees\CashierController;
use App\Models\AcademicYear;
use App\Models\Account;
use App\Models\Bill;
use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\SchoolSettings;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CashierTest extends TestCase
{
    use RefreshDatabase;

    private FeeType $spp;

    private User $finance;

    protected function setUp(): void
    {
        parent::setUp();

        Date::setTestNow('2026-07-10 03:00:00');

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

    private function septemberBill(Student $student, string $amount = '350000.00'): Bill
    {
        return Bill::factory()->recognized('2026-09-01')->create([
            'student_id' => $student->id,
            'fee_type_id' => $this->spp->id,
            'academic_year_id' => AcademicYear::query()->value('id') ?? AcademicYear::factory()->create()->id,
            'period_key' => '2026-09',
            'title' => 'SPP September 2026',
            'amount' => $amount,
            'due_date' => '2026-09-10',
        ]);
    }

    /**
     * @param  array<int, string>  $amounts  bill id => amount
     * @param  list<int>  $studentIds
     * @return array<string, mixed>
     */
    private function payload(string $received, array $amounts, array $studentIds, array $overrides = []): array
    {
        return [
            'amount_received' => $received,
            'student_ids' => $studentIds,
            'allocations' => collect($amounts)->map(fn (string $amount, int $billId) => ['bill_id' => $billId, 'amount' => $amount])->values()->all(),
            'deposit_account_id' => $this->accountId('1-1100'),
            ...$overrides,
        ];
    }

    public function test_one_cash_payment_settles_two_siblings_ac_14_04(): void
    {
        [$first, $second] = Student::factory()->count(2)->create();
        $billA = $this->septemberBill($first);
        $billB = $this->septemberBill($second);

        $this->actingAs($this->finance)
            ->post(route('fees.cashier.store'), $this->payload('700000', [$billA->id => '350000', $billB->id => '350000'], [$first->id, $second->id]))
            ->assertRedirect(route('fees.cashier.index'))
            ->assertInertiaFlash('payment.receipt_number', Payment::sole()->receipt_number);

        $payment = Payment::sole();
        $this->assertSame(PaymentStatus::Verified, $payment->status);
        $this->assertSame('700000.00', $payment->amount);
        $this->assertSame(2, PaymentAllocation::where('payment_id', $payment->id)->count());
        $this->assertSame(BillStatus::Paid, $billA->refresh()->status);
        $this->assertSame(BillStatus::Paid, $billB->refresh()->status);
        $this->assertNotEmpty($payment->receipt_number);
    }

    public function test_mismatched_allocation_is_rejected_ac_14_05(): void
    {
        $student = Student::factory()->create();
        $bill = $this->septemberBill($student);

        $this->actingAs($this->finance)
            ->postJson(route('fees.cashier.store'), $this->payload('400000', [$bill->id => '350000'], [$student->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount_received');

        $this->actingAs($this->finance)
            ->postJson(route('fees.cashier.store'), $this->payload('400000', [$bill->id => '400000'], [$student->id]))
            ->assertUnprocessable();

        $this->assertSame(0, Payment::count());
        $this->assertSame(BillStatus::Unpaid, $bill->refresh()->status);
    }

    public function test_non_cash_deposit_account_is_rejected(): void
    {
        $student = Student::factory()->create();
        $bill = $this->septemberBill($student);

        $this->actingAs($this->finance)
            ->postJson(route('fees.cashier.store'), $this->payload('350000', [$bill->id => '350000'], [$student->id], ['deposit_account_id' => $this->accountId('1-1300')]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('deposit_account_id');

        $this->assertSame(0, Payment::count());
    }

    public function test_search_by_name_and_nis_is_limited_and_siblings_share_a_guardian(): void
    {
        $class = SchoolClass::factory()->create();
        $student = Student::factory()->enrolledIn($class)->create(['full_name' => 'Budi Santoso', 'student_number' => '0012345']);
        $sibling = Student::factory()->create(['full_name' => 'Ani Santoso']);
        Student::factory()->create(['full_name' => 'Orang Lain']);
        $guardian = Guardian::factory()->create();
        $student->guardians()->attach($guardian);
        $sibling->guardians()->attach($guardian);
        $bill = $this->septemberBill($student);
        Bill::factory()->paid()->create(['student_id' => $student->id, 'fee_type_id' => $this->spp->id, 'academic_year_id' => $bill->academic_year_id, 'period_key' => '2026-08']);

        $this->actingAs($this->finance)
            ->get(route('fees.cashier.index', ['q' => '0012345']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('fees/cashier')
                ->has('results', 1)
                ->where('results.0.full_name', 'Budi Santoso')
                ->has('selectedStudents', 0));

        $this->actingAs($this->finance)
            ->get(route('fees.cashier.index', ['q' => 'santoso']))
            ->assertInertia(fn (Assert $page) => $page->has('results', 2));

        $this->actingAs($this->finance)
            ->get(route('fees.cashier.index', ['students' => [$student->id]]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('selectedStudents', 1)
                ->has('selectedStudents.0.bills', 1)
                ->where('selectedStudents.0.bills.0.id', $bill->id)
                ->where('selectedStudents.0.bills.0.remaining', '350000.00')
                ->has('siblings', 1)
                ->where('siblings.0.id', $sibling->id)
                ->where('defaultAccountId', $this->accountId('1-1100')));
    }

    public function test_only_admin_and_finance_may_use_the_cashier_ac_14_12(): void
    {
        $student = Student::factory()->create();
        $bill = $this->septemberBill($student);
        $payload = $this->payload('350000', [$bill->id => '350000'], [$student->id]);

        foreach ([User::factory()->principal(), User::factory()->teacher(), User::factory()->parent()] as $factory) {
            $user = $factory->create();

            $this->actingAs($user)->get(route('fees.cashier.index'))->assertForbidden();
            $this->actingAs($user)->post(route('fees.cashier.store'), $payload)->assertForbidden();
        }

        $this->assertSame(0, Payment::count());
        $this->actingAs(User::factory()->admin()->create())->get(route('fees.cashier.index'))->assertOk();
    }

    public function test_cashier_screen_preselects_configured_default_cash_account_ac_23_03(): void
    {
        $secondCash = Account::factory()->create([
            'code' => '1-1109',
            'name' => 'Kas Khusus',
            'is_cash' => true,
            'is_active' => true,
            'is_postable' => true,
        ]);

        app(SchoolSettings::class)->row()->update([
            'cashier_default_cash_account_id' => $secondCash->id,
        ]);
        app(SchoolSettings::class)->refresh();

        $this->actingAs($this->finance)
            ->get(route('fees.cashier.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('fees/cashier')
                ->where('defaultAccountId', $secondCash->id));
    }

    public function test_cashier_screen_falls_back_to_first_active_cash_account_when_setting_is_null_or_invalid_ac_23_04(): void
    {
        // 1. Setting is null -> falls back to first active cash account by code (1-1100)
        app(SchoolSettings::class)->row()->update([
            'cashier_default_cash_account_id' => null,
        ]);
        app(SchoolSettings::class)->refresh();

        $this->actingAs($this->finance)
            ->get(route('fees.cashier.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('fees/cashier')
                ->where('defaultAccountId', $this->accountId('1-1100')));

        // 2. Setting points to an inactive cash account -> falls back to first active cash account by code (1-1100)
        $inactiveCash = Account::factory()->create([
            'code' => '1-1105',
            'name' => 'Kas Nonaktif',
            'is_cash' => true,
            'is_active' => false,
            'is_postable' => true,
        ]);

        app(SchoolSettings::class)->row()->update([
            'cashier_default_cash_account_id' => $inactiveCash->id,
        ]);
        app(SchoolSettings::class)->refresh();

        $this->actingAs($this->finance)
            ->get(route('fees.cashier.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('fees/cashier')
                ->where('defaultAccountId', $this->accountId('1-1100')));

        // 3. Confirm CashierController contains no KAS_TUNAI_CODE constant
        $reflection = new \ReflectionClass(CashierController::class);
        $this->assertFalse($reflection->hasConstant('KAS_TUNAI_CODE'));
    }

    public function test_changing_cashier_default_account_does_not_alter_past_payments_ac_23_06(): void
    {
        $student = Student::factory()->create();
        $bill = $this->septemberBill($student);
        $originalCashId = $this->accountId('1-1100');

        $payload = $this->payload('350000', [$bill->id => '350000'], [$student->id], [
            'deposit_account_id' => $originalCashId,
        ]);

        $this->actingAs($this->finance)
            ->post(route('fees.cashier.store'), $payload)
            ->assertRedirect();

        $payment = Payment::firstOrFail();
        $this->assertSame($originalCashId, $payment->deposit_account_id);

        // Change cashier setting to a different cash account
        $newCash = Account::factory()->create([
            'code' => '1-1109',
            'name' => 'Kas Lain',
            'is_cash' => true,
            'is_active' => true,
            'is_postable' => true,
        ]);

        app(SchoolSettings::class)->row()->update([
            'cashier_default_cash_account_id' => $newCash->id,
        ]);
        app(SchoolSettings::class)->refresh();

        // Existing payment remains unchanged
        $payment->refresh();
        $this->assertSame($originalCashId, $payment->deposit_account_id);
    }
}

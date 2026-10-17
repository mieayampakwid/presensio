<?php

namespace Tests\Feature\Fees;

use App\Enums\BillStatus;
use App\Enums\JournalSource;
use App\Models\AcademicYear;
use App\Models\Account;
use App\Models\Bill;
use App\Models\FeeType;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentFeeDiscount;
use App\Models\User;
use App\Services\SchoolSettings;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class BillGenerationTest extends TestCase
{
    use RefreshDatabase;

    private SchoolClass $class;

    private FeeType $spp;

    protected function setUp(): void
    {
        parent::setUp();

        // Friday 2026-07-10, 10:00 Jakarta.
        Date::setTestNow('2026-07-10 03:00:00');

        app(SchoolSettings::class)->row();
        $this->seed(ChartOfAccountsSeeder::class);
        app(SchoolSettings::class)->refresh();

        AcademicYear::factory()->current()->create();
        $this->class = SchoolClass::factory()->create(['grade_level' => 5]);
        $this->spp = FeeType::factory()->create([
            'default_amount' => '350000.00',
            'receivable_account_id' => Account::where('code', '1-1300')->value('id'),
            'revenue_account_id' => Account::where('code', '4-1100')->value('id'),
            'discount_account_id' => Account::where('code', '4-1900')->value('id'),
        ]);
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'fee_type_id' => $this->spp->id,
            'target' => 'class',
            'class_id' => $this->class->id,
            'from_month' => '2026-09',
            'to_month' => '2026-09',
            'due_day' => 10,
            ...$overrides,
        ];
    }

    private function enrolled(?SchoolClass $class = null): Student
    {
        return Student::factory()->enrolledIn($class ?? $this->class)->create();
    }

    public function test_generation_creates_bill_with_expected_fields_ac_14_01(): void
    {
        $student = $this->enrolled();

        $this->actingAs(User::factory()->finance()->create())
            ->post(route('fees.generate.store'), $this->payload())
            ->assertRedirect(route('fees.generate.index'));

        $bill = Bill::where('student_id', $student->id)->sole();
        $this->assertSame('2026-09', $bill->period_key);
        $this->assertSame('350000.00', $bill->amount);
        $this->assertSame('0.00', $bill->paid_amount);
        $this->assertSame(BillStatus::Unpaid, $bill->status);
        $this->assertSame('2026-09-10', $bill->due_date->toDateString());
        $this->assertSame($this->class->academic_year_id, $bill->academic_year_id);
        $this->assertNull($bill->recognized_at);
    }

    public function test_rerun_skips_existing_bills_ac_14_02(): void
    {
        $this->enrolled();
        $finance = User::factory()->finance()->create();

        $this->actingAs($finance)->post(route('fees.generate.store'), $this->payload());
        $this->actingAs($finance)->postJson(route('fees.generate.preview'), $this->payload())
            ->assertOk()
            ->assertExactJson(['created' => 0, 'skipped' => 1, 'discounted' => 0]);
        $this->actingAs($finance)->post(route('fees.generate.store'), $this->payload());

        $this->assertSame(1, Bill::count());
    }

    public function test_discounts_apply_and_full_scholarship_is_waived_ac_14_03(): void
    {
        $half = $this->enrolled();
        $full = $this->enrolled();
        $none = $this->enrolled();
        $yearId = $this->class->academic_year_id;
        StudentFeeDiscount::factory()->create(['student_id' => $half->id, 'fee_type_id' => $this->spp->id, 'academic_year_id' => $yearId, 'percent' => '50', 'fixed_amount' => null, 'reason' => 'Beasiswa prestasi']);
        StudentFeeDiscount::factory()->create(['student_id' => $full->id, 'fee_type_id' => $this->spp->id, 'academic_year_id' => $yearId, 'percent' => '100', 'fixed_amount' => null]);

        $this->actingAs(User::factory()->finance()->create())
            ->postJson(route('fees.generate.preview'), $this->payload())
            ->assertExactJson(['created' => 3, 'skipped' => 0, 'discounted' => 2]);
        $this->assertSame(0, Bill::count());

        $this->actingAs(User::factory()->finance()->create())->post(route('fees.generate.store'), $this->payload());

        $halfBill = Bill::where('student_id', $half->id)->sole();
        $this->assertSame('175000.00', $halfBill->discount_amount);
        $this->assertSame('Beasiswa prestasi', $halfBill->discount_reason);
        $this->assertSame(BillStatus::Waived, Bill::where('student_id', $full->id)->sole()->status);
        $this->assertSame('0.00', Bill::where('student_id', $none->id)->sole()->discount_amount);
    }

    public function test_targets_resolve_through_enrollments_in_active_year(): void
    {
        $this->enrolled();
        $this->enrolled(SchoolClass::factory()->create(['grade_level' => 5]));
        $this->enrolled(SchoolClass::factory()->create(['grade_level' => 6]));
        Student::factory()->create();
        $finance = User::factory()->finance()->create();

        $count = fn (array $overrides) => $this->actingAs($finance)
            ->postJson(route('fees.generate.preview'), $this->payload($overrides))->json('created');

        $this->assertSame(1, $count([]));
        $this->assertSame(2, $count(['target' => 'grade_level', 'grade_level' => 5]));
        $this->assertSame(3, $count(['target' => 'all']));
    }

    public function test_recurring_range_recognizes_only_reached_months_ac_14_13(): void
    {
        $student = $this->enrolled();

        $this->actingAs(User::factory()->finance()->create())
            ->post(route('fees.generate.store'), $this->payload(['from_month' => '2026-07', 'to_month' => '2027-06', 'due_day' => 31]));

        $this->assertSame(12, Bill::where('student_id', $student->id)->count());
        $this->assertSame(['2026-07'], Bill::whereNotNull('recognized_at')->pluck('period_key')->all());
        $entry = JournalEntry::where('source_type', JournalSource::BillRecognition)->sole();
        $this->assertSame('2026-07-01', $entry->entry_date->toDateString());
        $this->assertSame('2027-02-28', Bill::where('period_key', '2027-02')->value('due_date')->toDateString());
    }

    public function test_one_time_fee_is_recognized_on_issue_date(): void
    {
        $this->enrolled();
        $uangPangkal = FeeType::factory()->oneTime()->create([
            'default_amount' => '5000000.00',
            'receivable_account_id' => $this->spp->receivable_account_id,
            'revenue_account_id' => Account::where('code', '4-1200')->value('id'),
            'discount_account_id' => $this->spp->discount_account_id,
        ]);

        $this->actingAs(User::factory()->finance()->create())
            ->post(route('fees.generate.store'), $this->payload(['fee_type_id' => $uangPangkal->id, 'due_date' => '2026-08-01']));

        $bill = Bill::sole();
        $this->assertSame('once', $bill->period_key);
        $this->assertSame('2026-07-10', $bill->recognized_at->toDateString());
        $this->assertSame('5000000.00', (string) JournalEntry::sole()->lines->firstWhere('account_id', $uangPangkal->revenue_account_id)->credit);
    }

    public function test_generation_validation_rejects_bad_input(): void
    {
        $finance = User::factory()->finance()->create();

        $this->actingAs($finance)->postJson(route('fees.generate.preview'), $this->payload(['from_month' => null, 'class_id' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors(['from_month', 'class_id']);
        $this->actingAs($finance)->postJson(route('fees.generate.preview'), $this->payload(['to_month' => '2030-12']))
            ->assertUnprocessable()->assertJsonValidationErrors(['to_month']);
        $this->actingAs($finance)->postJson(route('fees.generate.preview'), $this->payload(['fee_type_id' => FeeType::factory()->inactive()->create()->id]))
            ->assertUnprocessable()->assertJsonValidationErrors(['fee_type_id']);
    }

    public function test_finance_can_cancel_and_write_off_but_cancel_with_verified_payment_is_422(): void
    {
        $finance = User::factory()->finance()->create();
        $student = $this->enrolled();
        $bill = Bill::factory()->recognized('2026-07-01')->create(['student_id' => $student->id, 'fee_type_id' => $this->spp->id, 'period_key' => '2026-07']);
        PaymentAllocation::factory()->for(Payment::factory()->verified(), 'payment')->for($bill)->create(['amount' => '100000.00']);

        $this->actingAs($finance)->postJson(route('fees.bills.cancel', $bill), ['reason' => 'Salah'])->assertUnprocessable();
        $this->assertNull($bill->fresh()->cancelled_at);

        $this->actingAs($finance)->postJson(route('fees.bills.write-off', $bill), [])->assertUnprocessable()->assertJsonValidationErrors('reason');
    }

    public function test_bill_endpoints_adjust_discount_cancel_and_write_off(): void
    {
        $finance = User::factory()->finance()->create();
        $discounted = Bill::factory()->recognized('2026-07-01')->create(['fee_type_id' => $this->spp->id, 'period_key' => '2026-07']);
        $cancelled = Bill::factory()->create(['fee_type_id' => $this->spp->id, 'period_key' => '2026-08']);
        $writtenOff = Bill::factory()->recognized('2026-07-01')->create(['fee_type_id' => $this->spp->id, 'period_key' => '2026-06']);

        $this->actingAs($finance)->put(route('fees.bills.discount', $discounted), ['discount_amount' => '50000', 'reason' => 'Yatim'])->assertRedirect();
        $this->actingAs($finance)->post(route('fees.bills.cancel', $cancelled), ['reason' => 'Salah'])->assertRedirect();
        $this->actingAs($finance)->post(route('fees.bills.write-off', $writtenOff), ['reason' => 'Tidak tertagih'])->assertRedirect();

        $this->assertSame('50000.00', $discounted->fresh()->discount_amount);
        $this->assertSame(BillStatus::Cancelled, $cancelled->fresh()->status);
        $this->assertSame(BillStatus::WrittenOff, $writtenOff->fresh()->status);
    }

    public function test_only_finance_and_admin_can_use_generator_and_bill_actions(): void
    {
        $bill = Bill::factory()->recognized('2026-07-01')->create(['fee_type_id' => $this->spp->id, 'period_key' => '2026-07']);
        $this->enrolled();

        foreach ([User::factory()->admin(), User::factory()->finance()] as $user) {
            $this->actingAs($user->create())->get(route('fees.generate.index'))->assertOk();
        }

        foreach ([User::factory()->principal(), User::factory()->teacher(), User::factory()->parent()] as $user) {
            $actor = $user->create();
            $this->actingAs($actor)->get(route('fees.generate.index'))->assertForbidden();
            $this->actingAs($actor)->postJson(route('fees.generate.preview'), $this->payload())->assertForbidden();
            $this->actingAs($actor)->post(route('fees.generate.store'), $this->payload())->assertForbidden();
            $this->actingAs($actor)->put(route('fees.bills.discount', $bill), ['discount_amount' => '1', 'reason' => 'x'])->assertForbidden();
            $this->actingAs($actor)->post(route('fees.bills.cancel', $bill), ['reason' => 'x'])->assertForbidden();
            $this->actingAs($actor)->post(route('fees.bills.write-off', $bill), ['reason' => 'x'])->assertForbidden();
        }

        $this->assertSame(1, Bill::count());
        $this->assertSame('0.00', $bill->fresh()->discount_amount);
        $this->assertSame(BillStatus::Unpaid, $bill->fresh()->status);
    }
}

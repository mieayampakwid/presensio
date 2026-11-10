<?php

namespace Tests\Feature\Students;

use App\Enums\BillStatus;
use App\Enums\WithdrawalReason;
use App\Models\AcademicYear;
use App\Models\Bill;
use App\Models\FeeType;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\RfidCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\Finance\BillService;
use App\Services\SchoolSettings;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class StudentWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Student $student;

    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        Date::setTestNow('2026-11-09 08:00:00');
        $this->seed(ChartOfAccountsSeeder::class);
        app(SchoolSettings::class)->refresh();

        $this->admin = User::factory()->admin()->create();
        $year = AcademicYear::factory()->active()->create(['starts_at' => '2026-07-01', 'ends_at' => '2027-06-30']);
        $this->class = SchoolClass::factory()->create(['academic_year_id' => $year->id]);
        $this->student = Student::factory()->enrolledIn($this->class, '2026-07-01')->create();
    }

    protected function tearDown(): void
    {
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_non_admin_cannot_withdraw_student(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)
            ->post(route('students.withdraw', $this->student), [
                'ended_on' => '2026-11-09',
                'reason' => WithdrawalReason::TransferOut->value,
            ])
            ->assertForbidden();
    }

    public function test_withdrawal_preview_calculates_bill_impact(): void
    {
        $feeType1 = FeeType::factory()->create();
        $feeType2 = FeeType::factory()->create();

        Bill::factory()->create([
            'student_id' => $this->student->id,
            'fee_type_id' => $feeType1->id,
            'period_key' => '2026-12',
            'due_date' => '2026-12-10',
            'amount' => '300000.00',
        ]);

        $partial = Bill::factory()->create([
            'student_id' => $this->student->id,
            'fee_type_id' => $feeType2->id,
            'period_key' => '2026-12',
            'due_date' => '2026-12-10',
            'amount' => '300000.00',
        ]);
        PaymentAllocation::factory()->for(Payment::factory()->verified(), 'payment')->for($partial)->create(['amount' => '100000.00']);
        app(BillService::class)->recompute($partial);

        $response = $this->actingAs($this->admin)
            ->getJson(route('students.withdrawal-preview', $this->student))
            ->assertOk()
            ->json();

        $this->assertTrue($response['has_active_enrollment']);
        $this->assertSame($this->class->name, $response['class_name']);
        $this->assertSame(1, $response['cancelled_bills_count']);
        $this->assertSame(1, $response['review_bills_count']);
    }

    public function test_admin_can_withdraw_student_successfully(): void
    {
        $card = RfidCard::factory()->create(['student_id' => $this->student->id]);
        $user = User::factory()->student()->create();
        $this->student->update(['user_id' => $user->id]);

        $feeType = FeeType::factory()->create();
        $futureBill = Bill::factory()->create([
            'student_id' => $this->student->id,
            'fee_type_id' => $feeType->id,
            'period_key' => '2026-12',
            'due_date' => '2026-12-10',
            'amount' => '300000.00',
        ]);

        $this->actingAs($this->admin)
            ->post(route('students.withdraw', $this->student), [
                'ended_on' => '2026-11-09',
                'reason' => WithdrawalReason::TransferOut->value,
                'notes' => 'Pindah ke SMP 1 Bandung',
                'release_rfid' => true,
                'deactivate_user' => true,
            ])
            ->assertRedirect(route('students.index'));

        $enrollment = $this->student->enrollments()->firstOrFail();
        $this->assertSame('2026-11-09', $enrollment->ended_on->toDateString());
        $this->assertSame(WithdrawalReason::TransferOut, $enrollment->ended_reason);
        $this->assertSame('Pindah ke SMP 1 Bandung', $enrollment->ended_notes);

        $this->assertSame(BillStatus::Cancelled, $futureBill->fresh()->status);
        $this->assertNull($card->fresh()->student_id);
        $this->assertFalse($user->fresh()->is_active);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'student.withdrawn',
            'auditable_type' => $this->student->getMorphClass(),
            'auditable_id' => $this->student->id,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_withdrawal_fails_if_ended_on_is_before_started_on(): void
    {
        $this->actingAs($this->admin)
            ->post(route('students.withdraw', $this->student), [
                'ended_on' => '2026-06-30',
                'reason' => WithdrawalReason::DroppedOut->value,
            ])
            ->assertSessionHasErrors('ended_on');
    }
}

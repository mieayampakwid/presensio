<?php

namespace Tests\Feature\Excuses;

use App\Enums\AttendanceStatus;
use App\Enums\ExcuseStatus;
use App\Enums\ScanMethod;
use App\Models\Attendance;
use App\Models\Excuse;
use App\Models\Guardian;
use App\Models\NonSchoolDay;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExcuseApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Jakarta is already past midnight on Monday the 21st while UTC
        // still says Sunday the 20th — pins the school-tz default date.
        Date::setTestNow('2026-09-20 18:30:00', 'UTC');
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    public function test_approval_overwrites_and_injects_across_the_range(): void
    {
        $admin = User::factory()->admin()->create();
        $excuse = Excuse::factory()->create([
            'start_date' => '2026-09-21',
            'end_date' => '2026-09-23',
        ]);

        // Monday: a morning RFID tap — approval replaces it wholesale (the
        // tap survives in scan_events, not on this projection).
        $tapped = Attendance::factory()->create([
            'student_id' => $excuse->student_id,
            'date' => '2026-09-21',
            'status' => AttendanceStatus::Present,
            'checked_in_at' => Date::parse('2026-09-21 00:45:00', 'UTC'),
            'scan_method' => ScanMethod::Rfid,
            'override_by_user_id' => $admin->id,
            'notes' => 'Late arrival note',
        ]);
        // Tuesday: a swept absence — updated in place, same row.
        $swept = Attendance::factory()->absent()->create([
            'student_id' => $excuse->student_id,
            'date' => '2026-09-22',
        ]);

        $this->actingAs($admin)
            ->put(route('excuses.approve', ['excuse' => $excuse->id]), [
                'review_note' => 'Doctor note received.',
            ])
            ->assertRedirect();

        $excuse->refresh();
        $this->assertTrue($excuse->status === ExcuseStatus::Approved);
        $this->assertSame('Doctor note received.', $excuse->review_note);
        $this->assertSame($admin->id, $excuse->reviewed_by_user_id);

        $this->assertSame(3, Attendance::count());

        $monday = $tapped->refresh();
        $this->assertTrue($monday->status === AttendanceStatus::Sick);
        $this->assertNull($monday->checked_in_at);
        $this->assertNull($monday->checked_out_at);
        $this->assertNull($monday->scan_method);
        $this->assertNull($monday->override_by_user_id);
        $this->assertNull($monday->notes);

        $tuesday = $swept->refresh();
        $this->assertSame($swept->id, $tuesday->id);
        $this->assertTrue($tuesday->status === AttendanceStatus::Sick);
        $this->assertNull($tuesday->scan_method);

        $wednesday = Attendance::query()->where('date', '2026-09-23')->sole();
        $this->assertTrue($wednesday->status === AttendanceStatus::Sick);
        $this->assertNull($wednesday->scan_method);
    }

    public function test_approval_skips_weekends_and_non_school_days(): void
    {
        $admin = User::factory()->admin()->create();
        NonSchoolDay::factory()->create(['date' => '2026-09-24', 'name' => 'Cuti Bersama']);
        $excuse = Excuse::factory()->create([
            'start_date' => '2026-09-24', // holiday
            'end_date' => '2026-09-27', // Fri + weekend
        ]);

        $this->actingAs($admin)
            ->put(route('excuses.approve', ['excuse' => $excuse->id]))
            ->assertRedirect();

        // Only Friday the 25th is a school day in the range.
        $this->assertSame(1, Attendance::count());
        $friday = Attendance::query()->sole();
        $this->assertSame('2026-09-25', $friday->date->toDateString());
    }

    public function test_leave_excuses_map_to_leave_attendance(): void
    {
        $admin = User::factory()->admin()->create();
        $excuse = Excuse::factory()->leave()->create([
            'start_date' => '2026-09-21',
            'end_date' => '2026-09-21',
        ]);

        $this->actingAs($admin)
            ->put(route('excuses.approve', ['excuse' => $excuse->id]))
            ->assertRedirect();

        $this->assertTrue(
            Attendance::query()->sole()->status === AttendanceStatus::Leave,
        );
    }

    public function test_approving_a_resolved_excuse_is_a_no_op(): void
    {
        $firstAdmin = User::factory()->admin()->create();
        $secondAdmin = User::factory()->admin()->create();
        $excuse = Excuse::factory()->create([
            'start_date' => '2026-09-21',
            'end_date' => '2026-09-21',
        ]);

        $this->actingAs($firstAdmin)
            ->put(route('excuses.approve', ['excuse' => $excuse->id]), [
                'review_note' => 'First review.',
            ])
            ->assertRedirect();

        $this->actingAs($secondAdmin)
            ->put(route('excuses.approve', ['excuse' => $excuse->id]), [
                'review_note' => 'Second look.',
            ])
            ->assertRedirect();

        $excuse->refresh();
        // Terminal states: the second review changes nothing.
        $this->assertTrue($excuse->status === ExcuseStatus::Approved);
        $this->assertSame('First review.', $excuse->review_note);
        $this->assertSame($firstAdmin->id, $excuse->reviewed_by_user_id);
        $this->assertSame(1, Attendance::count());
    }

    public function test_rejection_stamps_the_review_but_touches_no_attendance(): void
    {
        $admin = User::factory()->admin()->create();
        $excuse = Excuse::factory()->create([
            'start_date' => '2026-09-21',
            'end_date' => '2026-09-22',
        ]);
        $tap = Attendance::factory()->create([
            'student_id' => $excuse->student_id,
            'date' => '2026-09-21',
        ]);

        $this->actingAs($admin)
            ->put(route('excuses.reject', ['excuse' => $excuse->id]), [
                'review_note' => 'Proof is unreadable.',
            ])
            ->assertRedirect();

        $excuse->refresh();
        $this->assertTrue($excuse->status === ExcuseStatus::Rejected);
        $this->assertSame('Proof is unreadable.', $excuse->review_note);
        $this->assertSame($admin->id, $excuse->reviewed_by_user_id);

        // The Monday tap stands untouched; Tuesday was never created.
        $this->assertTrue($tap->refresh()->status === AttendanceStatus::Present);
        $this->assertSame(1, Attendance::count());
    }

    public function test_rejecting_an_approved_excuse_is_a_no_op(): void
    {
        $admin = User::factory()->admin()->create();
        $excuse = Excuse::factory()->create([
            'start_date' => '2026-09-21',
            'end_date' => '2026-09-21',
        ]);

        $this->actingAs($admin)
            ->put(route('excuses.approve', ['excuse' => $excuse->id]))
            ->assertRedirect();

        $this->actingAs($admin)
            ->put(route('excuses.reject', ['excuse' => $excuse->id]), [
                'review_note' => 'Changed my mind.',
            ])
            ->assertRedirect();

        $excuse->refresh();
        $this->assertTrue($excuse->status === ExcuseStatus::Approved);
        $this->assertNull($excuse->review_note);
        $this->assertSame(1, Attendance::count());
    }

    public function test_an_approved_future_excuse_pre_blocks_the_absence_sweep(): void
    {
        $admin = User::factory()->admin()->create();
        $excusedStudent = Student::factory()->create();
        $bareStudent = Student::factory()->create();

        Excuse::factory()->create([
            'student_id' => $excusedStudent->id,
            'start_date' => '2026-09-22',
            'end_date' => '2026-09-23',
        ]);

        $this->actingAs($admin)
            ->put(route('excuses.approve', ['excuse' => Excuse::query()->sole()->id]))
            ->assertRedirect();

        // Tuesday rolls around; the sweep fires for students without a
        // record for the day.
        Date::setTestNow('2026-09-21 18:30:00', 'UTC'); // school date: Tue the 22nd

        $this->artisan('attendance:mark-absences')->assertSuccessful();

        $excused = Attendance::query()
            ->where('student_id', $excusedStudent->id)
            ->whereDate('date', '2026-09-22')
            ->sole();
        $this->assertTrue($excused->status === AttendanceStatus::Sick);
        $this->assertNull($excused->scan_method);

        // Control: the unexcused student was swept to absent as usual.
        $swept = Attendance::query()->where('student_id', $bareStudent->id)->sole();
        $this->assertTrue($swept->status === AttendanceStatus::Absent);
        $this->assertNull($swept->scan_method);
    }

    #[DataProvider('staffActions')]
    public function test_only_admins_can_resolve_excuses(string $route, string $factoryState): void
    {
        $user = User::factory()->{$factoryState}()->create();

        if ($factoryState === 'parent') {
            Guardian::factory()->create(['user_id' => $user->id]);
        }

        if ($factoryState === 'teacher') {
            Teacher::factory()->create(['user_id' => $user->id]);
        }

        $excuse = Excuse::factory()->create();

        $this->actingAs($user)
            ->put(route($route, ['excuse' => $excuse->id]))
            ->assertForbidden();

        $this->assertTrue($excuse->refresh()->status === ExcuseStatus::Pending);
    }

    public static function staffActions(): array
    {
        return [
            ['excuses.approve', 'teacher'],
            ['excuses.approve', 'parent'],
            ['excuses.approve', 'student'],
            ['excuses.reject', 'teacher'],
            ['excuses.reject', 'parent'],
            ['excuses.reject', 'student'],
        ];
    }
}

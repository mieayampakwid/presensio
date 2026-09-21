<?php

namespace Tests\Feature\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\ScanMethod;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExceptionDashboardTest extends TestCase
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

    private function teacherUser(): User
    {
        $user = User::factory()->teacher()->create();
        Teacher::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    public function test_teacher_sees_only_their_own_classes(): void
    {
        $user = $this->teacherUser();
        $teacher = $user->teacher;
        $own = SchoolClass::factory()->create(['name' => 'Kelas 5A', 'teacher_id' => $teacher->id]);
        SchoolClass::factory()->create(['name' => 'Kelas 5B']);
        Student::factory()->enrolledIn($own)->create(['full_name' => 'Own Student']);
        Student::factory()->create(['full_name' => 'Other Student']);

        $this->actingAs($user)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('attendance/index')
                ->has('classes', 1)
                ->where('classes.0.name', 'Kelas 5A')
                ->has('rows', 1)
                ->where('rows.0.full_name', 'Own Student'));
    }

    public function test_admin_sees_all_classes(): void
    {
        $admin = User::factory()->admin()->create();
        $first = SchoolClass::factory()->create(['name' => 'Kelas 5A']);
        SchoolClass::factory()->create(['name' => 'Kelas 5B']);
        Student::factory()->count(2)->enrolledIn($first)->create();

        $this->actingAs($admin)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('classes', 2)
                ->has('rows', 2));
    }

    public function test_teacher_without_linked_profile_sees_an_empty_dashboard(): void
    {
        $user = User::factory()->teacher()->create();

        $this->actingAs($user)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('classes', 0)
                ->has('rows', 0));
    }

    public function test_default_date_is_the_school_timezone_today(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.date', '2026-09-21'));
    }

    public function test_upsert_creates_then_updates_the_same_record(): void
    {
        $user = $this->teacherUser();
        $teacher = $user->teacher;
        $class = SchoolClass::factory()->create(['teacher_id' => $teacher->id]);
        $student = Student::factory()->enrolledIn($class)->create();

        $this->actingAs($user)
            ->put(route('attendance.record.update'), [
                'student_id' => $student->id,
                'date' => '2026-09-21',
                'status' => 'late',
                'checked_in_at' => '07:45',
                'notes' => 'Scanner was offline',
            ])
            ->assertRedirect();

        $record = Attendance::query()->sole();
        $this->assertTrue($record->status === AttendanceStatus::Late);
        $this->assertTrue($record->scan_method === ScanMethod::ManualOverride);
        $this->assertSame($user->id, $record->override_by_user_id);
        $this->assertSame('Scanner was offline', $record->notes);
        $this->assertSame('2026-09-21', $record->date->toDateString());
        // 07:45 Jakarta is 00:45 UTC.
        $this->assertSame(
            Date::parse('2026-09-21 00:45:00', 'UTC')->toIso8601String(),
            $record->checked_in_at->toIso8601String(),
        );

        $this->actingAs($user)
            ->put(route('attendance.record.update'), [
                'student_id' => $student->id,
                'date' => '2026-09-21',
                'status' => 'sick',
            ])
            ->assertRedirect();

        $updated = Attendance::query()->sole();
        $this->assertSame($record->id, $updated->id);
        $this->assertTrue($updated->status === AttendanceStatus::Sick);
        $this->assertNull($updated->checked_in_at);
        $this->assertSame('2026-09-21', $updated->date->toDateString());
    }

    public function test_checkout_before_checkin_is_rejected(): void
    {
        $user = $this->teacherUser();
        $teacher = $user->teacher;
        $class = SchoolClass::factory()->create(['teacher_id' => $teacher->id]);
        $student = Student::factory()->enrolledIn($class)->create();

        $this->actingAs($user)
            ->put(route('attendance.record.update'), [
                'student_id' => $student->id,
                'date' => '2026-09-21',
                'status' => 'present',
                'checked_in_at' => '08:00',
                'checked_out_at' => '07:00',
            ])
            ->assertSessionHasErrors('checked_out_at');

        $this->assertSame(0, Attendance::count());
    }

    public function test_bulk_present_fills_only_the_gaps(): void
    {
        $user = $this->teacherUser();
        $teacher = $user->teacher;
        $class = SchoolClass::factory()->create(['teacher_id' => $teacher->id]);
        $absent = Student::factory()->enrolledIn($class)->create();
        $sick = Student::factory()->enrolledIn($class)->create();
        $gap = Student::factory()->enrolledIn($class)->create();

        $absentRecord = Attendance::factory()->absent()->create([
            'student_id' => $absent->id,
            'date' => '2026-09-21',
        ]);
        $sickRecord = Attendance::factory()->create([
            'student_id' => $sick->id,
            'date' => '2026-09-21',
            'status' => 'sick',
        ]);

        $this->actingAs($user)
            ->post(route('attendance.bulk-present'), [
                'class_id' => $class->id,
                'date' => '2026-09-21',
            ])
            ->assertRedirect();

        $this->assertSame(3, Attendance::count());

        $created = $gap->refresh()->attendances->sole();
        $this->assertTrue($created->status === AttendanceStatus::Present);
        $this->assertTrue($created->scan_method === ScanMethod::ManualOverride);
        $this->assertSame($user->id, $created->override_by_user_id);
        $this->assertSame('Bulk marked present', $created->notes);

        $this->assertTrue($absentRecord->refresh()->status === AttendanceStatus::Absent);
        $this->assertTrue($sickRecord->refresh()->status === AttendanceStatus::Sick);
    }

    #[DataProvider('nonStaffRoles')]
    public function test_students_and_parents_are_forbidden(string $factoryState): void
    {
        $user = User::factory()->{$factoryState}()->create();

        $this->actingAs($user)
            ->get(route('attendance.index'))
            ->assertForbidden();
    }

    public static function nonStaffRoles(): array
    {
        return [
            ['student'],
            ['parent'],
        ];
    }

    public function test_teacher_cannot_edit_another_teachers_student(): void
    {
        $user = $this->teacherUser();
        $outsider = Student::factory()->create();

        $this->actingAs($user)
            ->put(route('attendance.record.update'), [
                'student_id' => $outsider->id,
                'date' => '2026-09-21',
                'status' => 'present',
            ])
            ->assertForbidden();

        $this->assertSame(0, Attendance::count());
    }

    public function test_teacher_cannot_bulk_mark_another_teachers_class(): void
    {
        $user = $this->teacherUser();
        $class = SchoolClass::factory()->create();

        $this->actingAs($user)
            ->post(route('attendance.bulk-present'), [
                'class_id' => $class->id,
                'date' => '2026-09-21',
            ])
            ->assertForbidden();

        $this->assertSame(0, Attendance::count());
    }
}

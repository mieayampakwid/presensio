<?php

namespace Tests\Feature\Auth;

use App\Enums\Ability;
use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Attendance\ClassAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class MultiRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * AC-15-01: A user with both teacher and parent roles can take attendance
     * for their homeroom class and view attendance reports for their linked children.
     */
    public function test_user_with_teacher_and_parent_can_write_attendance_for_homeroom_and_view_child_report(): void
    {
        $year = AcademicYear::factory()->create(['is_active' => true]);
        $teacher = Teacher::factory()->create();
        $class = SchoolClass::factory()->create([
            'academic_year_id' => $year->id,
            'teacher_id' => $teacher->id,
        ]);

        $child = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $child->id,
            'class_id' => $class->id,
            'started_on' => now()->subMonth(),
            'ended_on' => null,
        ]);

        $guardian = Guardian::factory()->create();
        $guardian->students()->attach($child->id, ['relationship_type' => 'parent']);

        $user = User::factory()->teacher()->create();
        $user->roleGrants()->create(['role' => UserRole::Parent]);

        $teacher->update(['user_id' => $user->id]);
        $guardian->update(['user_id' => $user->id]);

        $this->assertTrue(ClassAccess::canWrite($user, $class->id));

        // Submit attendance for homeroom student
        $response = $this->actingAs($user)->put(route('attendance.record.update'), [
            'student_id' => $child->id,
            'date' => now()->toDateString(),
            'status' => AttendanceStatus::Present->value,
        ]);
        $response->assertRedirect();

        // Access child report
        $reportResponse = $this->actingAs($user)->get(route('reports.student', [
            'student_id' => $child->id,
        ]));
        $reportResponse->assertOk();
    }

    /**
     * AC-15-02: Assigning student alongside any other role returns HTTP 422 with
     * validation error ('The student role cannot be combined with other roles.').
     */
    public function test_student_role_cannot_be_combined_with_other_roles(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'username' => 'student_multi',
            'roles' => [UserRole::Student->value, UserRole::Parent->value],
            'password' => 'S3cure-p4ss!word',
            'password_confirmation' => 'S3cure-p4ss!word',
        ]);

        $response->assertSessionHasErrors(['roles' => 'The student role cannot be combined with other roles.']);

        $existingUser = User::factory()->student()->create();

        $updateResponse = $this->actingAs($admin)->put(route('users.update', $existingUser), [
            'username' => $existingUser->username,
            'roles' => [UserRole::Student->value, UserRole::Teacher->value],
            'is_active' => true,
        ]);

        $updateResponse->assertSessionHasErrors(['roles' => 'The student role cannot be combined with other roles.']);
    }

    /**
     * AC-15-03: Demoting or deactivating the only active admin returns HTTP 422 with
     * validation error ('The system must have at least one active administrator.').
     */
    public function test_cannot_demote_or_deactivate_last_active_admin(): void
    {
        // Keep only 1 active admin
        User::query()->delete();
        $admin = User::factory()->admin()->create(['is_active' => true]);

        // Attempt to demote admin to teacher
        $demoteResponse = $this->actingAs($admin)->put(route('users.update', $admin), [
            'username' => $admin->username,
            'roles' => [UserRole::Teacher->value],
            'is_active' => true,
        ]);
        $demoteResponse->assertSessionHasErrors(['roles' => 'The system must have at least one active administrator.']);

        // Attempt to deactivate admin
        $deactivateResponse = $this->actingAs($admin)->put(route('users.update', $admin), [
            'username' => $admin->username,
            'roles' => [UserRole::Admin->value],
            'is_active' => false,
        ]);
        $deactivateResponse->assertSessionHasErrors(['is_active' => 'The system must have at least one active administrator.']);

        // Creating a second admin allows modifying the first
        $secondAdmin = User::factory()->admin()->create(['is_active' => true]);
        $allowedResponse = $this->actingAs($secondAdmin)->put(route('users.update', $admin), [
            'username' => $admin->username,
            'roles' => [UserRole::Teacher->value],
            'is_active' => true,
        ]);
        $allowedResponse->assertSessionHasNoErrors();
    }

    /**
     * AC-15-04: Principal cannot override attendance or bulk-mark present, but can view reports.
     */
    public function test_principal_cannot_override_or_bulk_fill_attendance_but_can_view_reports(): void
    {
        $year = AcademicYear::factory()->create(['is_active' => true]);
        $class = SchoolClass::factory()->create(['academic_year_id' => $year->id]);
        $student = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'started_on' => now()->subMonth(),
            'ended_on' => null,
        ]);

        $principal = User::factory()->principal()->create();

        // Principal write attempts are rejected (403)
        $this->actingAs($principal)->put(route('attendance.record.update'), [
            'student_id' => $student->id,
            'date' => now()->toDateString(),
            'status' => AttendanceStatus::Present->value,
        ])->assertForbidden();

        $this->actingAs($principal)->post(route('attendance.bulk-present'), [
            'class_id' => $class->id,
            'date' => now()->toDateString(),
        ])->assertForbidden();

        // Principal can view reports (200)
        $this->actingAs($principal)->get(route('reports.class'))->assertOk();
        $this->actingAs($principal)->get(route('presence-board.index'))->assertOk();
        $this->actingAs($principal)->get(route('reports.student', [
            'student_id' => $student->id,
        ]))->assertOk();
    }

    /**
     * AC-15-05: Finance role cannot enter grades.
     */
    public function test_finance_cannot_enter_grades(): void
    {
        $finance = User::factory()->finance()->create();

        $this->assertFalse(Gate::forUser($finance)->allows(Ability::EnterGrades->value));
    }

    /**
     * Password reset contact lookup prefers teacher profile phone,
     * then guardian profile phone, then users.email.
     */
    public function test_password_reset_contact_lookup_prefers_teacher_then_guardian_then_email(): void
    {
        $user = User::factory()->teacher()->create([
            'email' => 'user@presensio.test',
        ]);
        $user->roleGrants()->create(['role' => UserRole::Parent]);

        $teacher = Teacher::factory()->create([
            'user_id' => $user->id,
            'phone_number' => '08111111111',
        ]);
        $guardian = Guardian::factory()->create([
            'user_id' => $user->id,
            'phone_number' => '08222222222',
        ]);

        $this->assertEquals('08111111111', $user->passwordResetContact());

        // Without teacher phone, falls back to guardian
        $teacher->update(['phone_number' => null]);
        $user->unsetRelation('teacher');
        $this->assertEquals('08222222222', $user->passwordResetContact());

        // Without guardian either, falls back to user email
        $guardian->update(['user_id' => null]);
        $user->unsetRelation('guardian');
        $this->assertEquals('user@presensio.test', $user->passwordResetContact());
    }
}

<?php

namespace Tests\Feature\Navigation;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PhaseOneNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_principal_can_access_notification_deliveries_route(): void
    {
        $admin = User::factory()->admin()->create();
        $principal = User::factory()->principal()->create();

        $this->actingAs($admin)
            ->get(route('notification-deliveries.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/notifications/deliveries'));

        $this->actingAs($principal)
            ->get(route('notification-deliveries.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/notifications/deliveries'));
    }

    public function test_admin_can_access_notification_settings_route(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/settings/notifications')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('settings/notifications'));
    }

    public function test_classes_listing_supplies_classes_with_ids_for_grade_ledger_and_report_cards(): void
    {
        $admin = User::factory()->admin()->create();
        $class = SchoolClass::factory()->create();

        $this->actingAs($admin)
            ->get(route('classes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('classes/index')
                ->has('classes.data', 1)
                ->where('classes.data.0.id', $class->id)
            );
    }

    public function test_parent_dashboard_supplies_linked_children_for_grades_navigation(): void
    {
        $parentUser = User::factory()->parent()->create();
        $guardian = Guardian::factory()->create(['user_id' => $parentUser->id]);
        $student = Student::factory()->create();
        $guardian->students()->attach($student->id, ['relationship_type' => 'mother']);

        $academicYear = AcademicYear::factory()->current()->create();
        $class = SchoolClass::factory()->create(['academic_year_id' => $academicYear->id]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'started_on' => '2026-07-01',
            'ended_on' => null,
        ]);

        $this->actingAs($parentUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->has('children', 1)
                ->where('children.0.id', $student->id)
                ->where('children.0.full_name', $student->full_name)
            );
    }

    public function test_course_show_supplies_class_subject_id_for_results_navigation(): void
    {
        $teacherUser = User::factory()->teacher()->create();
        $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);
        $class = SchoolClass::factory()->create(['teacher_id' => $teacher->id]);
        $subject = Subject::factory()->create();
        $classSubject = ClassSubject::factory()->create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);

        $this->actingAs($teacherUser)
            ->get(route('courses.show', $classSubject))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('teacher/courses/show')
                ->where('course.id', $classSubject->id)
            );
    }
}

<?php

namespace Tests\Feature\Teacher;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * AC-09-04: Teacher Budi sees Class 5A Matematika at /courses.
     */
    public function test_ac_09_04_teacher_sees_assigned_courses_with_roster_count_at_courses_page(): void
    {
        $activeYear = AcademicYear::active() ?? AcademicYear::factory()->create(['is_active' => true]);

        $budiUser = User::factory()->teacher()->create();
        $budiTeacher = Teacher::factory()->create([
            'user_id' => $budiUser->id,
            'name' => 'Budi Raharjo',
        ]);

        $class5A = SchoolClass::factory()->create([
            'academic_year_id' => $activeYear->id,
            'name' => 'Kelas 5A',
        ]);

        $matematika = Subject::factory()->create([
            'name' => 'Matematika',
            'code' => 'MAT',
        ]);

        $classSubject = ClassSubject::factory()->create([
            'class_id' => $class5A->id,
            'subject_id' => $matematika->id,
            'teacher_id' => $budiTeacher->id,
            'passing_threshold' => '75.00',
        ]);

        // Enroll 3 students as of today
        $student1 = Student::factory()->create();
        $student2 = Student::factory()->create();
        $student3 = Student::factory()->create();

        Enrollment::factory()->create([
            'student_id' => $student1->id,
            'class_id' => $class5A->id,
            'started_on' => now()->subMonth()->toDateString(),
            'ended_on' => null,
        ]);
        Enrollment::factory()->create([
            'student_id' => $student2->id,
            'class_id' => $class5A->id,
            'started_on' => now()->subMonth()->toDateString(),
            'ended_on' => null,
        ]);
        Enrollment::factory()->create([
            'student_id' => $student3->id,
            'class_id' => $class5A->id,
            'started_on' => now()->subMonth()->toDateString(),
            'ended_on' => null,
        ]);

        // A student who left yesterday should NOT be counted in today's roster
        $studentPast = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $studentPast->id,
            'class_id' => $class5A->id,
            'started_on' => now()->subMonths(2)->toDateString(),
            'ended_on' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($budiUser)
            ->get(route('courses.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('teacher/courses/index')
                ->has('courses', 1)
                ->where('courses.0.class_name', 'Kelas 5A')
                ->where('courses.0.subject_name', 'Matematika')
                ->where('courses.0.subject_code', 'MAT')
                ->where('courses.0.passing_threshold', '75.00')
                ->where('courses.0.students_count', 3)
            );
    }

    /**
     * AC-09-05: Teacher Siti (neither 5A's homeroom teacher nor holding any 5A assignment)
     * requesting 5A course data receives HTTP 403.
     */
    public function test_ac_09_05_teacher_siti_without_homeroom_or_assignment_receives_403(): void
    {
        $activeYear = AcademicYear::active() ?? AcademicYear::factory()->create(['is_active' => true]);

        $sitiUser = User::factory()->teacher()->create();
        $sitiTeacher = Teacher::factory()->create([
            'user_id' => $sitiUser->id,
            'name' => 'Siti Aminah',
        ]);

        $class5A = SchoolClass::factory()->create([
            'academic_year_id' => $activeYear->id,
            'name' => 'Kelas 5A',
        ]);

        $matematika = Subject::factory()->create([
            'name' => 'Matematika',
            'code' => 'MAT',
        ]);

        $budiTeacher = Teacher::factory()->create(['name' => 'Budi Raharjo']);

        $course5A = ClassSubject::factory()->create([
            'class_id' => $class5A->id,
            'subject_id' => $matematika->id,
            'teacher_id' => $budiTeacher->id,
        ]);

        // Siti has neither homeroom in 5A nor assignment in 5A -> 403
        $this->actingAs($sitiUser)
            ->get(route('courses.show', $course5A))
            ->assertForbidden();
    }

    public function test_assigned_teacher_can_view_course_workspace(): void
    {
        $activeYear = AcademicYear::active() ?? AcademicYear::factory()->create(['is_active' => true]);

        $teacherUser = User::factory()->teacher()->create();
        $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);

        $class = SchoolClass::factory()->create(['academic_year_id' => $activeYear->id]);
        $subject = Subject::factory()->create(['name' => 'Fisika']);

        $course = ClassSubject::factory()->create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);

        $student = Student::factory()->create(['full_name' => 'Andi Wijaya']);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'started_on' => now()->subDay()->toDateString(),
            'ended_on' => null,
        ]);

        $this->actingAs($teacherUser)
            ->get(route('courses.show', $course))
            ->assertOk()
            ->assertSee('Fisika')
            ->assertSee('Andi Wijaya');
    }

    public function test_homeroom_teacher_can_view_course_workspace_even_if_not_teaching(): void
    {
        $activeYear = AcademicYear::active() ?? AcademicYear::factory()->create(['is_active' => true]);

        $homeroomUser = User::factory()->teacher()->create();
        $homeroomTeacher = Teacher::factory()->create(['user_id' => $homeroomUser->id]);

        $class = SchoolClass::factory()->create([
            'academic_year_id' => $activeYear->id,
            'teacher_id' => $homeroomTeacher->id,
        ]);

        $otherTeacher = Teacher::factory()->create();
        $subject = Subject::factory()->create(['name' => 'Kimia']);

        $course = ClassSubject::factory()->create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $otherTeacher->id,
        ]);

        $this->actingAs($homeroomUser)
            ->get(route('courses.show', $course))
            ->assertOk()
            ->assertSee('Kimia');
    }
}

<?php

namespace Tests\Feature\Classes;

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

class ClassSubjectVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private SchoolClass $class;

    private Subject $subject;

    private Teacher $teacher;

    private ClassSubject $classSubject;

    protected function setUp(): void
    {
        parent::setUp();

        $activeYear = AcademicYear::active() ?? AcademicYear::factory()->create(['is_active' => true]);

        $homeroomTeacher = Teacher::factory()->create();

        $this->class = SchoolClass::factory()->create([
            'academic_year_id' => $activeYear->id,
            'teacher_id' => $homeroomTeacher->id,
            'name' => 'Kelas 5A',
        ]);

        $this->subject = Subject::factory()->create([
            'name' => 'Matematika',
            'code' => 'MAT',
        ]);

        $this->teacher = Teacher::factory()->create(['name' => 'Budi Raharjo']);

        $this->classSubject = ClassSubject::factory()->create([
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'passing_threshold' => '75.00',
        ]);
    }

    public function test_admin_sees_editable_view_with_thresholds(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('classes.subjects.index', $this->class))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('classes/subjects')
                ->has('defaultPassingThreshold')
                ->where('classSubjects.0.passing_threshold', '75.00')
            );
    }

    public function test_principal_sees_read_only_view_without_thresholds(): void
    {
        $principal = User::factory()->principal()->create();

        $this->actingAs($principal)
            ->get(route('classes.subjects.index', $this->class))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('classes/show-subjects')
                ->has('classSubjects', 1)
                ->where('classSubjects.0.subject_name', 'Matematika')
                ->where('classSubjects.0.teacher_name', 'Budi Raharjo')
                ->missing('classSubjects.0.passing_threshold')
            );
    }

    public function test_homeroom_teacher_sees_read_only_view_without_thresholds(): void
    {
        $homeroomUser = User::factory()->teacher()->create();
        $this->class->teacher->update(['user_id' => $homeroomUser->id]);

        $this->actingAs($homeroomUser)
            ->get(route('classes.subjects.index', $this->class))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('classes/show-subjects')
                ->where('classSubjects.0.subject_name', 'Matematika')
                ->missing('classSubjects.0.passing_threshold')
            );
    }

    public function test_unrelated_teacher_receives_403(): void
    {
        $otherUser = User::factory()->teacher()->create();
        Teacher::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAs($otherUser)
            ->get(route('classes.subjects.index', $this->class))
            ->assertForbidden();
    }

    public function test_parent_of_enrolled_child_sees_read_only_view_without_thresholds(): void
    {
        $parentUser = User::factory()->parent()->create();
        $guardian = Guardian::factory()->create(['user_id' => $parentUser->id]);
        $student = Student::factory()->create();
        $guardian->students()->attach($student->id, ['relationship_type' => 'mother']);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'class_id' => $this->class->id,
            'started_on' => now()->subMonth()->toDateString(),
            'ended_on' => null,
        ]);

        $this->actingAs($parentUser)
            ->get(route('classes.subjects.index', $this->class))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('classes/show-subjects')
                ->where('classSubjects.0.subject_name', 'Matematika')
                ->missing('classSubjects.0.passing_threshold')
            );
    }

    public function test_parent_of_child_in_another_class_receives_403(): void
    {
        $otherClass = SchoolClass::factory()->create();

        $parentUser = User::factory()->parent()->create();
        $guardian = Guardian::factory()->create(['user_id' => $parentUser->id]);
        $student = Student::factory()->create();
        $guardian->students()->attach($student->id, ['relationship_type' => 'father']);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'class_id' => $otherClass->id,
            'started_on' => now()->subMonth()->toDateString(),
            'ended_on' => null,
        ]);

        $this->actingAs($parentUser)
            ->get(route('classes.subjects.index', $this->class))
            ->assertForbidden();
    }

    public function test_student_enrolled_in_class_sees_read_only_view_without_thresholds(): void
    {
        $studentUser = User::factory()->student()->create();
        $student = Student::factory()->create(['user_id' => $studentUser->id]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'class_id' => $this->class->id,
            'started_on' => now()->subMonth()->toDateString(),
            'ended_on' => null,
        ]);

        $this->actingAs($studentUser)
            ->get(route('classes.subjects.index', $this->class))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('classes/show-subjects')
                ->where('classSubjects.0.subject_name', 'Matematika')
                ->missing('classSubjects.0.passing_threshold')
            );
    }

    public function test_student_in_another_class_receives_403(): void
    {
        $otherClass = SchoolClass::factory()->create();

        $studentUser = User::factory()->student()->create();
        $student = Student::factory()->create(['user_id' => $studentUser->id]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'class_id' => $otherClass->id,
            'started_on' => now()->subMonth()->toDateString(),
            'ended_on' => null,
        ]);

        $this->actingAs($studentUser)
            ->get(route('classes.subjects.index', $this->class))
            ->assertForbidden();
    }
}

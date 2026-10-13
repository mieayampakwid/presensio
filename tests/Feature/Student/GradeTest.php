<?php

namespace Tests\Feature\Student;

use App\Enums\AssessmentKind;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Score;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GradeTest extends TestCase
{
    use RefreshDatabase;

    private User $studentUser;

    private Student $student;

    private AcademicYear $academicYear;

    private Semester $semester;

    private SchoolClass $schoolClass;

    private ClassSubject $classSubject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->academicYear = AcademicYear::factory()->create([
            'name' => '2026/2027',
            'starts_at' => '2026-07-01',
            'ends_at' => '2027-06-30',
            'is_active' => true,
        ]);

        $this->semester = $this->academicYear->semesters()->where('number', 1)->first()
            ?? Semester::factory()->create([
                'academic_year_id' => $this->academicYear->id,
                'number' => 1,
                'starts_at' => '2026-07-01',
                'ends_at' => '2026-12-31',
            ]);

        $this->studentUser = User::factory()->student()->create();
        $this->student = Student::factory()->create([
            'user_id' => $this->studentUser->id,
            'full_name' => 'Doni Siregar',
        ]);

        $teacher = Teacher::factory()->create();
        $this->schoolClass = SchoolClass::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level' => 5,
            'teacher_id' => $teacher->id,
        ]);

        Enrollment::create([
            'student_id' => $this->student->id,
            'class_id' => $this->schoolClass->id,
            'started_on' => '2026-07-01',
            'ended_on' => null,
        ]);

        $subject = Subject::factory()->create(['name' => 'Matematika']);
        $this->classSubject = ClassSubject::factory()->create([
            'class_id' => $this->schoolClass->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'passing_threshold' => '75.00',
        ]);
    }

    public function test_student_sees_only_own_released_assessments(): void
    {
        // Unreleased assessment
        $unreleased = Assessment::factory()->create([
            'class_subject_id' => $this->classSubject->id,
            'semester_id' => $this->semester->id,
            'name' => 'Formatif 1',
            'kind' => AssessmentKind::Formative,
            'assessed_on' => '2026-08-10',
            'released_at' => null,
        ]);

        Score::factory()->create([
            'assessment_id' => $unreleased->id,
            'student_id' => $this->student->id,
            'score' => 70,
        ]);

        // Released assessment
        $released = Assessment::factory()->create([
            'class_subject_id' => $this->classSubject->id,
            'semester_id' => $this->semester->id,
            'name' => 'Sumatif Lingkup 1',
            'kind' => AssessmentKind::SummativeScope,
            'assessed_on' => '2026-08-20',
            'released_at' => now(),
        ]);

        Score::factory()->create([
            'assessment_id' => $released->id,
            'student_id' => $this->student->id,
            'score' => 88,
            'remedial_score' => null,
            'notes' => 'Kerja bagus!',
        ]);

        $response = $this->actingAs($this->studentUser)
            ->get(route('my-grades.index', [
                'semester' => $this->semester->id,
            ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('student/grades')
            ->where('student.id', $this->student->id)
            ->has('subjects.0.assessments', 1)
            ->where('subjects.0.assessments.0.id', $released->id)
            ->where('subjects.0.assessments.0.name', 'Sumatif Lingkup 1')
            ->where('subjects.0.assessments.0.score', 88)
            ->where('subjects.0.assessments.0.notes', 'Kerja bagus!')
        );
    }

    public function test_payload_never_leaks_final_score_or_description_to_student(): void
    {
        $released = Assessment::factory()->create([
            'class_subject_id' => $this->classSubject->id,
            'semester_id' => $this->semester->id,
            'name' => 'Sumatif Lingkup 1',
            'released_at' => now(),
        ]);

        Score::factory()->create([
            'assessment_id' => $released->id,
            'student_id' => $this->student->id,
            'score' => 88,
        ]);

        $response = $this->actingAs($this->studentUser)
            ->get(route('my-grades.index', [
                'semester' => $this->semester->id,
            ]));

        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $subjects = $props['subjects'];
        $this->assertNotEmpty($subjects);
        foreach ($subjects as $sub) {
            $this->assertArrayNotHasKey('final_score', $sub);
            $this->assertArrayNotHasKey('description', $sub);
            $this->assertArrayNotHasKey('live_score', $sub);
            foreach ($sub['assessments'] as $assessment) {
                $this->assertArrayNotHasKey('final_score', $assessment);
                $this->assertArrayNotHasKey('description', $assessment);
            }
        }
    }
}

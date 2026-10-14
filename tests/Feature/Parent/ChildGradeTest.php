<?php

namespace Tests\Feature\Parent;

use App\Enums\AssessmentKind;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Guardian;
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

class ChildGradeTest extends TestCase
{
    use RefreshDatabase;

    private User $guardianUser;

    private Guardian $guardian;

    private User $otherGuardianUser;

    private Guardian $otherGuardian;

    private Student $child;

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

        $this->guardianUser = User::factory()->parent()->create();
        $this->guardian = Guardian::factory()->create(['user_id' => $this->guardianUser->id]);

        $this->otherGuardianUser = User::factory()->parent()->create();
        $this->otherGuardian = Guardian::factory()->create(['user_id' => $this->otherGuardianUser->id]);

        $this->child = Student::factory()->create(['full_name' => 'Rian Hidayat']);
        $this->guardian->students()->attach($this->child->id, ['relationship_type' => 'father']);

        $teacher = Teacher::factory()->create();
        $this->schoolClass = SchoolClass::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level' => 5,
            'teacher_id' => $teacher->id,
        ]);

        Enrollment::create([
            'student_id' => $this->child->id,
            'class_id' => $this->schoolClass->id,
            'started_on' => '2026-07-01',
            'ended_on' => null,
        ]);

        $subject = Subject::factory()->create(['name' => 'IPAS']);
        $this->classSubject = ClassSubject::factory()->create([
            'class_id' => $this->schoolClass->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'passing_threshold' => '75.00',
        ]);
    }

    public function test_unreleased_assessment_absent_and_appears_after_release(): void
    {
        // Unreleased assessment
        $unreleased = Assessment::factory()->create([
            'class_subject_id' => $this->classSubject->id,
            'semester_id' => $this->semester->id,
            'name' => 'Kuis IPAS Rahasia',
            'kind' => AssessmentKind::Formative,
            'assessed_on' => '2026-08-10',
            'released_at' => null,
        ]);

        Score::factory()->create([
            'assessment_id' => $unreleased->id,
            'student_id' => $this->child->id,
            'score' => 80,
        ]);

        // Released assessment
        $released = Assessment::factory()->create([
            'class_subject_id' => $this->classSubject->id,
            'semester_id' => $this->semester->id,
            'name' => 'Sumatif Ekosistem',
            'kind' => AssessmentKind::SummativeScope,
            'assessed_on' => '2026-08-20',
            'released_at' => now(),
        ]);

        Score::factory()->create([
            'assessment_id' => $released->id,
            'student_id' => $this->child->id,
            'score' => 95,
        ]);

        // Request child grades
        $response = $this->actingAs($this->guardianUser)
            ->get(route('children.grades.index', [
                'student' => $this->child->id,
                'semester' => $this->semester->id,
            ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('parent/child-grades')
            ->has('subjects.0.assessments', 1)
            ->where('subjects.0.assessments.0.id', $released->id)
            ->where('subjects.0.assessments.0.name', 'Sumatif Ekosistem')
            ->where('subjects.0.assessments.0.score', 95)
        );

        // Now release the unreleased assessment
        $unreleased->update(['released_at' => now()]);

        // Request child grades again -> both appear
        $responseAfter = $this->actingAs($this->guardianUser)
            ->get(route('children.grades.index', [
                'student' => $this->child->id,
                'semester' => $this->semester->id,
            ]));

        $responseAfter->assertOk();
        $responseAfter->assertInertia(fn (Assert $page) => $page
            ->has('subjects.0.assessments', 2)
        );
    }

    public function test_unlinked_child_returns_403(): void
    {
        $response = $this->actingAs($this->otherGuardianUser)
            ->get(route('children.grades.index', [
                'student' => $this->child->id,
                'semester' => $this->semester->id,
            ]));

        $response->assertForbidden();
    }

    public function test_payload_never_leaks_final_score_or_description_to_guardian(): void
    {
        // Released assessment
        $released = Assessment::factory()->create([
            'class_subject_id' => $this->classSubject->id,
            'semester_id' => $this->semester->id,
            'name' => 'Sumatif Bab 1',
            'released_at' => now(),
        ]);

        Score::factory()->create([
            'assessment_id' => $released->id,
            'student_id' => $this->child->id,
            'score' => 90,
        ]);

        $response = $this->actingAs($this->guardianUser)
            ->get(route('children.grades.index', [
                'student' => $this->child->id,
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

    public function test_admin_can_view_child_grades(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->get(route('children.grades.index', [
                'student' => $this->child->id,
                'semester' => $this->semester->id,
            ]));

        $response->assertOk();
    }
}

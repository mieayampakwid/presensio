<?php

namespace Tests\Feature\Teacher;

use App\Enums\AssessmentKind;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Models\CourseSemester;
use App\Models\Enrollment;
use App\Models\LearningObjective;
use App\Models\SchoolClass;
use App\Models\Score;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradebookTest extends TestCase
{
    use RefreshDatabase;

    private User $teacherUser;

    private Teacher $teacher;

    private User $otherTeacherUser;

    private Teacher $otherTeacher;

    private AcademicYear $academicYear;

    private Semester $semester;

    private Subject $subject;

    private SchoolClass $schoolClass;

    private ClassSubject $classSubject;

    private LearningObjective $objective;

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

        $this->teacherUser = User::factory()->teacher()->create();
        $this->teacher = Teacher::factory()->create(['user_id' => $this->teacherUser->id]);

        $this->otherTeacherUser = User::factory()->teacher()->create();
        $this->otherTeacher = Teacher::factory()->create(['user_id' => $this->otherTeacherUser->id]);

        $this->subject = Subject::factory()->create();

        $this->schoolClass = SchoolClass::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level' => 5,
        ]);

        $this->classSubject = ClassSubject::factory()->create([
            'class_id' => $this->schoolClass->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'passing_threshold' => '75.00',
        ]);

        $this->objective = LearningObjective::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'subject_id' => $this->subject->id,
            'grade_level' => 5,
            'code' => 'TP 1',
        ]);
    }

    public function test_teacher_can_view_gradebook_with_assessments_and_students(): void
    {
        $student = Student::factory()->create(['full_name' => 'Budi Santoso']);
        Enrollment::create([
            'student_id' => $student->id,
            'class_id' => $this->schoolClass->id,
            'started_on' => '2026-07-01',
            'ended_on' => null,
        ]);

        $assessment = Assessment::factory()->create([
            'class_subject_id' => $this->classSubject->id,
            'semester_id' => $this->semester->id,
            'name' => 'Sumatif 1',
            'kind' => AssessmentKind::SummativeScope,
            'assessed_on' => '2026-08-15',
            'max_score' => '100.00',
        ]);

        Score::factory()->create([
            'assessment_id' => $assessment->id,
            'student_id' => $student->id,
            'score' => '85.00',
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->get(route('courses.gradebook', [
                'class_subject' => $this->classSubject->id,
                'semester' => $this->semester->id,
            ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('teacher/gradebook')
            ->has('students', 1)
            ->has('assessments', 1)
            ->has('weights')
            ->where('canWrite', true)
        );
    }

    public function test_ac_10_13_transferred_in_student_absent_from_pre_transfer_assessment_cells(): void
    {
        // Student A enrolled from day 1
        $studentA = Student::factory()->create(['full_name' => 'Anisa']);
        Enrollment::create([
            'student_id' => $studentA->id,
            'class_id' => $this->schoolClass->id,
            'started_on' => '2026-07-01',
            'ended_on' => null,
        ]);

        // Student B transferred in on 2026-10-01
        $studentB = Student::factory()->create(['full_name' => 'Bambang']);
        Enrollment::create([
            'student_id' => $studentB->id,
            'class_id' => $this->schoolClass->id,
            'started_on' => '2026-10-01',
            'ended_on' => null,
        ]);

        // Assessment 1: 2026-09-20 (Before B transferred in)
        $assessment1 = Assessment::factory()->create([
            'class_subject_id' => $this->classSubject->id,
            'semester_id' => $this->semester->id,
            'name' => 'Asesmen September',
            'assessed_on' => '2026-09-20',
        ]);

        // Assessment 2: 2026-10-15 (After B transferred in)
        $assessment2 = Assessment::factory()->create([
            'class_subject_id' => $this->classSubject->id,
            'semester_id' => $this->semester->id,
            'name' => 'Asesmen Oktober',
            'assessed_on' => '2026-10-15',
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->get(route('courses.gradebook', [
                'class_subject' => $this->classSubject->id,
                'semester' => $this->semester->id,
            ]));

        $response->assertOk();

        $students = $response->viewData('page')['props']['students'];

        // Student A has cells for both assessment 1 and 2
        $rowA = collect($students)->firstWhere('id', $studentA->id);
        $this->assertNotNull($rowA);
        $this->assertArrayHasKey($assessment1->id, $rowA['cells']);
        $this->assertArrayHasKey($assessment2->id, $rowA['cells']);

        // Student B is absent from assessment 1's cells, but present for assessment 2 (AC-10-13)
        $rowB = collect($students)->firstWhere('id', $studentB->id);
        $this->assertNotNull($rowB);
        $this->assertArrayNotHasKey($assessment1->id, $rowB['cells']);
        $this->assertArrayHasKey($assessment2->id, $rowB['cells']);
    }

    public function test_gradebook_computes_live_score_status_and_complete_flag(): void
    {
        // 50/50 weights
        CourseSemester::forCourse($this->classSubject, $this->semester)->update([
            'scope_weight' => '50.00',
            'final_weight' => '50.00',
        ]);

        $student = Student::factory()->create(['full_name' => 'Cahya']);
        Enrollment::create([
            'student_id' => $student->id,
            'class_id' => $this->schoolClass->id,
            'started_on' => '2026-07-01',
            'ended_on' => null,
        ]);

        $scope1 = Assessment::factory()->create([
            'class_subject_id' => $this->classSubject->id,
            'semester_id' => $this->semester->id,
            'kind' => AssessmentKind::SummativeScope,
            'assessed_on' => '2026-08-01',
            'max_score' => 100,
        ]);
        $scope2 = Assessment::factory()->create([
            'class_subject_id' => $this->classSubject->id,
            'semester_id' => $this->semester->id,
            'kind' => AssessmentKind::SummativeScope,
            'assessed_on' => '2026-09-01',
            'max_score' => 100,
        ]);
        $final = Assessment::factory()->create([
            'class_subject_id' => $this->classSubject->id,
            'semester_id' => $this->semester->id,
            'kind' => AssessmentKind::SummativeFinal,
            'assessed_on' => '2026-11-01',
            'max_score' => 100,
        ]);

        // AC-10-02 numbers: scope 80 & 70 (avg 75), final 90 -> 82.5 -> rounded 83
        Score::factory()->create([
            'assessment_id' => $scope1->id,
            'student_id' => $student->id,
            'score' => '80.00',
        ]);
        Score::factory()->create([
            'assessment_id' => $scope2->id,
            'student_id' => $student->id,
            'score' => '70.00',
        ]);
        Score::factory()->create([
            'assessment_id' => $final->id,
            'student_id' => $student->id,
            'score' => '90.00',
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->get(route('courses.gradebook', [
                'class_subject' => $this->classSubject->id,
                'semester' => $this->semester->id,
            ]));

        $response->assertOk();
        $students = $response->viewData('page')['props']['students'];
        $row = collect($students)->firstWhere('id', $student->id);

        $this->assertEquals(83, $row['live_score']);
        $this->assertSame('Tuntas', $row['status']);
        $this->assertTrue($row['complete']);
    }

    public function test_gradebook_incomplete_when_scores_are_missing(): void
    {
        $student = Student::factory()->create(['full_name' => 'Dimas']);
        Enrollment::create([
            'student_id' => $student->id,
            'class_id' => $this->schoolClass->id,
            'started_on' => '2026-07-01',
            'ended_on' => null,
        ]);

        $scope1 = Assessment::factory()->create([
            'class_subject_id' => $this->classSubject->id,
            'semester_id' => $this->semester->id,
            'kind' => AssessmentKind::SummativeScope,
            'assessed_on' => '2026-08-01',
            'max_score' => 100,
        ]);
        $scope2 = Assessment::factory()->create([
            'class_subject_id' => $this->classSubject->id,
            'semester_id' => $this->semester->id,
            'kind' => AssessmentKind::SummativeScope,
            'assessed_on' => '2026-09-01',
            'max_score' => 100,
        ]);

        // Only scope 1 is filled
        Score::factory()->create([
            'assessment_id' => $scope1->id,
            'student_id' => $student->id,
            'score' => '80.00',
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->get(route('courses.gradebook', [
                'class_subject' => $this->classSubject->id,
                'semester' => $this->semester->id,
            ]));

        $response->assertOk();
        $students = $response->viewData('page')['props']['students'];
        $row = collect($students)->firstWhere('id', $student->id);

        $this->assertEquals(80, $row['live_score']);
        $this->assertFalse($row['complete']);
    }

    public function test_unauthorized_teacher_cannot_access_gradebook_returns_403(): void
    {
        $response = $this->actingAs($this->otherTeacherUser)
            ->get(route('courses.gradebook', [
                'class_subject' => $this->classSubject->id,
                'semester' => $this->semester->id,
            ]));

        $response->assertForbidden();
    }
}

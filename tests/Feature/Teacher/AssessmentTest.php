<?php

namespace Tests\Feature\Teacher;

use App\Enums\AssessmentKind;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Models\CourseSemester;
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

class AssessmentTest extends TestCase
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
        ]);

        $this->objective = LearningObjective::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'subject_id' => $this->subject->id,
            'grade_level' => 5,
            'code' => 'TP 1',
            'description' => 'Memahami pecahan',
        ]);
    }

    public function test_teacher_can_view_assessments_for_assigned_course(): void
    {
        Assessment::factory()->create([
            'class_subject_id' => $this->classSubject->id,
            'semester_id' => $this->semester->id,
            'name' => 'Sumatif 1',
            'kind' => AssessmentKind::SummativeScope,
            'assessed_on' => '2026-08-15',
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->get(route('courses.assessments.index', [
                'class_subject' => $this->classSubject->id,
                'semester' => $this->semester->id,
            ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('teacher/assessments/index')
            ->has('assessments', 1)
            ->where('canWrite', true)
        );
    }

    public function test_summative_scope_without_tps_returns_422(): void
    {
        // AC-10-05: Creating a summative_scope assessment without any linked TP returns HTTP 422
        $response = $this->actingAs($this->teacherUser)
            ->postJson(route('courses.assessments.store', $this->classSubject), [
                'semester_id' => $this->semester->id,
                'name' => 'Sumatif Lingkup Materi 1',
                'kind' => 'summative_scope',
                'assessed_on' => '2026-08-15',
                'max_score' => 100,
                'objective_ids' => [],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('objective_ids');
    }

    public function test_teacher_can_create_formative_and_summative_assessments(): void
    {
        // Formative can be created without TPs
        $response1 = $this->actingAs($this->teacherUser)
            ->post(route('courses.assessments.store', $this->classSubject), [
                'semester_id' => $this->semester->id,
                'name' => 'Formatif Kuis 1',
                'kind' => 'formative',
                'assessed_on' => '2026-08-10',
                'max_score' => 100,
            ]);

        $response1->assertRedirect();
        $this->assertDatabaseHas('assessments', [
            'class_subject_id' => $this->classSubject->id,
            'name' => 'Formatif Kuis 1',
            'kind' => 'formative',
        ]);

        // Summative scope with TP links
        $response2 = $this->actingAs($this->teacherUser)
            ->post(route('courses.assessments.store', $this->classSubject), [
                'semester_id' => $this->semester->id,
                'name' => 'Sumatif Lingkup Materi: Pecahan',
                'kind' => 'summative_scope',
                'assessed_on' => '2026-08-20',
                'max_score' => 100,
                'objective_ids' => [$this->objective->id],
            ]);

        $response2->assertRedirect();
        $this->assertDatabaseHas('assessments', [
            'class_subject_id' => $this->classSubject->id,
            'name' => 'Sumatif Lingkup Materi: Pecahan',
            'kind' => 'summative_scope',
        ]);
        $this->assertDatabaseHas('assessment_objectives', [
            'learning_objective_id' => $this->objective->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'auditable_type' => Assessment::class,
        ]);
    }

    public function test_assessment_date_must_be_within_semester_range(): void
    {
        // Semester starts 2026-07-01 and ends 2026-12-31
        $response = $this->actingAs($this->teacherUser)
            ->postJson(route('courses.assessments.store', $this->classSubject), [
                'semester_id' => $this->semester->id,
                'name' => 'Sumatif Diluar Semester',
                'kind' => 'formative',
                'assessed_on' => '2027-02-15',
                'max_score' => 100,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('assessed_on');
    }

    public function test_unassigned_teacher_cannot_manage_assessments_returns_403(): void
    {
        // AC-10-11: Teacher Siti saving scores/assessments for 5A IPAS receives HTTP 403
        $response = $this->actingAs($this->otherTeacherUser)
            ->post(route('courses.assessments.store', $this->classSubject), [
                'semester_id' => $this->semester->id,
                'name' => 'Asesmen Liar',
                'kind' => 'formative',
                'assessed_on' => '2026-08-15',
                'max_score' => 100,
            ]);

        $response->assertForbidden();
    }

    public function test_cannot_delete_assessment_with_scores_without_confirm_returns_422(): void
    {
        $assessment = Assessment::factory()->create([
            'class_subject_id' => $this->classSubject->id,
            'semester_id' => $this->semester->id,
        ]);

        $student = Student::factory()->create();
        Score::factory()->create([
            'assessment_id' => $assessment->id,
            'student_id' => $student->id,
            'score' => '80.00',
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->delete(route('courses.assessments.destroy', [
                'class_subject' => $this->classSubject->id,
                'assessment' => $assessment->id,
            ]));

        $response->assertStatus(422);
        $this->assertDatabaseHas('assessments', ['id' => $assessment->id]);
    }

    public function test_deleting_assessment_with_confirm_flag_deletes_and_audits_scores(): void
    {
        $assessment = Assessment::factory()->create([
            'class_subject_id' => $this->classSubject->id,
            'semester_id' => $this->semester->id,
            'name' => 'Asesmen Dihapus',
        ]);

        $student = Student::factory()->create();
        $score = Score::factory()->create([
            'assessment_id' => $assessment->id,
            'student_id' => $student->id,
            'score' => '85.00',
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->delete(route('courses.assessments.destroy', [
                'class_subject' => $this->classSubject->id,
                'assessment' => $assessment->id,
            ]), ['confirm' => true]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('assessments', ['id' => $assessment->id]);
        $this->assertDatabaseMissing('scores', ['id' => $score->id]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deleted',
            'auditable_type' => Assessment::class,
            'auditable_id' => $assessment->id,
        ]);
    }

    public function test_cannot_mutate_assessments_on_finalized_course_semester_returns_422(): void
    {
        $courseSemester = CourseSemester::forCourse($this->classSubject, $this->semester);
        $courseSemester->update([
            'finalized_at' => now(),
            'finalized_by_user_id' => $this->teacherUser->id,
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->postJson(route('courses.assessments.store', $this->classSubject), [
                'semester_id' => $this->semester->id,
                'name' => 'Asesmen Pada Semester Final',
                'kind' => 'formative',
                'assessed_on' => '2026-08-15',
                'max_score' => 100,
            ]);

        $response->assertStatus(422);
    }

    public function test_assessment_tps_must_belong_to_course_academic_scope(): void
    {
        $foreignTp = LearningObjective::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'subject_id' => $this->subject->id,
            'grade_level' => 6, // Grade 6 instead of course's Grade 5
            'code' => 'TP Grade 6',
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->postJson(route('courses.assessments.store', $this->classSubject), [
                'semester_id' => $this->semester->id,
                'name' => 'Sumatif Salah Jenjang',
                'kind' => 'summative_scope',
                'assessed_on' => '2026-08-15',
                'max_score' => 100,
                'objective_ids' => [$foreignTp->id],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('objective_ids');
    }
}

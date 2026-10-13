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
use App\Support\ReportCardGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseFinalizationTest extends TestCase
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

    private Student $student;

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

        $this->subject = Subject::factory()->create(['name' => 'Matematika']);

        $this->schoolClass = SchoolClass::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level' => 5,
            'name' => '5A',
        ]);

        $this->classSubject = ClassSubject::factory()->create([
            'class_id' => $this->schoolClass->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'passing_threshold' => '75.00',
        ]);

        $this->student = Student::factory()->create(['full_name' => 'Budi Santoso']);
        Enrollment::create([
            'student_id' => $this->student->id,
            'class_id' => $this->schoolClass->id,
            'started_on' => '2026-07-01',
            'ended_on' => null,
        ]);
    }

    public function test_ac_10_08_finalizing_with_missing_scores_fails_and_succeeds_when_complete(): void
    {
        $tp = LearningObjective::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'subject_id' => $this->subject->id,
            'grade_level' => 5,
            'code' => 'TP 1',
            'description' => 'memahami bilangan bulat',
        ]);

        $a1 = Assessment::factory()->create([
            'class_subject_id' => $this->classSubject->id,
            'semester_id' => $this->semester->id,
            'name' => 'Sumatif 1',
            'kind' => AssessmentKind::SummativeScope,
            'assessed_on' => '2026-08-10',
            'max_score' => '100.00',
        ]);
        $a1->learningObjectives()->attach($tp->id);

        $a2 = Assessment::factory()->create([
            'class_subject_id' => $this->classSubject->id,
            'semester_id' => $this->semester->id,
            'name' => 'Sumatif 2',
            'kind' => AssessmentKind::SummativeScope,
            'assessed_on' => '2026-09-10',
            'max_score' => '100.00',
        ]);
        $a2->learningObjectives()->attach($tp->id);

        // Student only has score for a1, missing a2
        Score::factory()->create([
            'assessment_id' => $a1->id,
            'student_id' => $this->student->id,
            'score' => '85.00',
        ]);

        // Attempt finalization -> 422 with missing details
        $res = $this->actingAs($this->teacherUser)
            ->postJson(route('courses.finalize', $this->classSubject), [
                'semester_id' => $this->semester->id,
            ]);

        $res->assertStatus(422);
        $res->assertJsonStructure(['missing']);
        $this->assertCount(1, $res->json('missing'));
        $this->assertEquals($this->student->id, $res->json('missing.0.student.id'));
        $this->assertEquals($a2->id, $res->json('missing.0.assessment.id'));

        // Fill missing score
        Score::factory()->create([
            'assessment_id' => $a2->id,
            'student_id' => $this->student->id,
            'score' => '95.00',
        ]);

        // Finalize succeeds
        $resSuccess = $this->actingAs($this->teacherUser)
            ->post(route('courses.finalize', $this->classSubject), [
                'semester_id' => $this->semester->id,
            ]);

        $resSuccess->assertRedirect();

        $courseSemester = CourseSemester::forCourse($this->classSubject, $this->semester);
        $this->assertTrue($courseSemester->isFinalized());
        $this->assertNotNull($courseSemester->finalized_at);
        $this->assertEquals($this->teacherUser->id, $courseSemester->finalized_by_user_id);

        // SubjectResult created with frozen final_score
        $this->assertDatabaseHas('subject_results', [
            'course_semester_id' => $courseSemester->id,
            'student_id' => $this->student->id,
            'final_score' => 90, // (85 + 95) / 2
        ]);

        // Audited
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $courseSemester->getMorphClass(),
            'auditable_id' => $courseSemester->id,
            'action' => 'finalized',
        ]);
    }

    public function test_ac_10_09_editing_score_in_finalized_course_returns_422(): void
    {
        $a1 = Assessment::factory()->create([
            'class_subject_id' => $this->classSubject->id,
            'semester_id' => $this->semester->id,
            'kind' => AssessmentKind::SummativeFinal,
            'assessed_on' => '2026-11-10',
            'max_score' => '100.00',
        ]);

        $score = Score::factory()->create([
            'assessment_id' => $a1->id,
            'student_id' => $this->student->id,
            'score' => '80.00',
        ]);

        // Finalize course
        $courseSemester = CourseSemester::forCourse($this->classSubject, $this->semester);
        $courseSemester->update([
            'finalized_at' => now(),
            'finalized_by_user_id' => $this->teacherUser->id,
        ]);

        // Attempt editing score via store scores batch
        $res = $this->actingAs($this->teacherUser)
            ->postJson(route('courses.scores.store', $this->classSubject), [
                'scores' => [
                    [
                        'assessment_id' => $a1->id,
                        'student_id' => $this->student->id,
                        'score' => 90,
                    ],
                ],
            ]);

        $res->assertStatus(422);
    }

    public function test_ac_10_10_undoing_finalization_blocked_when_report_cards_published(): void
    {
        $courseSemester = CourseSemester::forCourse($this->classSubject, $this->semester);
        $courseSemester->update([
            'finalized_at' => now(),
            'finalized_by_user_id' => $this->teacherUser->id,
        ]);

        // Guard with mutable flag
        $guard = new class extends ReportCardGuard
        {
            public bool $published = true;

            public function isPublished(ClassSubject $classSubject, Semester $semester): bool
            {
                return $this->published;
            }
        };
        $this->app->instance(ReportCardGuard::class, $guard);

        $res = $this->actingAs($this->teacherUser)
            ->delete(route('courses.unfinalize', [
                'class_subject' => $this->classSubject->id,
                'semester_id' => $this->semester->id,
            ]));

        $res->assertStatus(422);
        $this->assertTrue($courseSemester->fresh()->isFinalized());

        // Now set published = false
        $guard->published = false;

        $resUndo = $this->actingAs($this->teacherUser)
            ->delete(route('courses.unfinalize', [
                'class_subject' => $this->classSubject->id,
                'semester_id' => $this->semester->id,
            ]));
        $resUndo->assertRedirect();
        $this->assertFalse($courseSemester->fresh()->isFinalized());
        $this->assertNull($courseSemester->fresh()->finalized_at);

        // Audit log unfinalized
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $courseSemester->getMorphClass(),
            'auditable_id' => $courseSemester->id,
            'action' => 'unfinalized',
        ]);
    }

    public function test_mutations_blocked_when_course_is_finalized(): void
    {
        $tp = LearningObjective::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'subject_id' => $this->subject->id,
            'grade_level' => 5,
        ]);

        $assessment = Assessment::factory()->create([
            'class_subject_id' => $this->classSubject->id,
            'semester_id' => $this->semester->id,
            'kind' => AssessmentKind::SummativeScope,
            'assessed_on' => '2026-08-10',
        ]);
        $assessment->learningObjectives()->attach($tp->id);

        $courseSemester = CourseSemester::forCourse($this->classSubject, $this->semester);
        $courseSemester->update([
            'finalized_at' => now(),
            'finalized_by_user_id' => $this->teacherUser->id,
        ]);

        // 1. Create assessment blocked
        $this->actingAs($this->teacherUser)
            ->postJson(route('courses.assessments.store', $this->classSubject), [
                'name' => 'Asesmen Baru',
                'kind' => AssessmentKind::SummativeScope->value,
                'assessed_on' => '2026-08-20',
                'semester_id' => $this->semester->id,
                'max_score' => 100,
                'objective_ids' => [$tp->id],
            ])
            ->assertStatus(422);

        // 2. Update assessment blocked
        $this->actingAs($this->teacherUser)
            ->putJson(route('courses.assessments.update', [$this->classSubject, $assessment]), [
                'name' => 'Nama Baru',
                'kind' => AssessmentKind::SummativeScope->value,
                'assessed_on' => '2026-08-15',
                'max_score' => 100,
                'objective_ids' => [$tp->id],
            ])
            ->assertStatus(422);

        // 3. Delete assessment blocked
        $this->actingAs($this->teacherUser)
            ->delete(route('courses.assessments.destroy', [$this->classSubject, $assessment]))
            ->assertStatus(422);

        // 4. Update weights blocked
        $this->actingAs($this->teacherUser)
            ->putJson(route('courses.weights.update', $this->classSubject), [
                'semester_id' => $this->semester->id,
                'scope_weight' => 60,
                'final_weight' => 40,
            ])
            ->assertStatus(422);

        // 5. Update description blocked
        $this->actingAs($this->teacherUser)
            ->putJson(route('courses.results.description.update', [$this->classSubject, $this->student]), [
                'semester_id' => $this->semester->id,
                'description' => 'Deskripsi baru',
            ])
            ->assertStatus(422);
    }

    public function test_curriculum_locked_returns_true_when_any_course_is_finalized(): void
    {
        $this->assertFalse($this->schoolClass->curriculumLocked());

        $courseSemester = CourseSemester::forCourse($this->classSubject, $this->semester);
        $courseSemester->update([
            'finalized_at' => now(),
            'finalized_by_user_id' => $this->teacherUser->id,
        ]);

        $this->assertTrue($this->schoolClass->curriculumLocked());
    }

    public function test_authorization_for_finalization(): void
    {
        // Other teacher cannot finalize or unfinalize
        $this->actingAs($this->otherTeacherUser)
            ->post(route('courses.finalize', $this->classSubject), [
                'semester_id' => $this->semester->id,
            ])
            ->assertForbidden();

        $this->actingAs($this->otherTeacherUser)
            ->delete(route('courses.unfinalize', $this->classSubject), [
                'semester_id' => $this->semester->id,
            ])
            ->assertForbidden();

        // Admin can finalize
        $admin = User::factory()->admin()->create();

        // Add complete score for student
        $assessment = Assessment::factory()->create([
            'class_subject_id' => $this->classSubject->id,
            'semester_id' => $this->semester->id,
            'kind' => AssessmentKind::SummativeFinal,
            'assessed_on' => '2026-11-10',
        ]);
        Score::factory()->create([
            'assessment_id' => $assessment->id,
            'student_id' => $this->student->id,
            'score' => 88,
        ]);

        $this->actingAs($admin)
            ->post(route('courses.finalize', $this->classSubject), [
                'semester_id' => $this->semester->id,
            ])
            ->assertRedirect();

        $this->assertTrue(CourseSemester::forCourse($this->classSubject, $this->semester)->isFinalized());

        // Admin can unfinalize
        $this->actingAs($admin)
            ->delete(route('courses.unfinalize', $this->classSubject), [
                'semester_id' => $this->semester->id,
            ])
            ->assertRedirect();

        $this->assertFalse(CourseSemester::forCourse($this->classSubject, $this->semester)->isFinalized());
    }
}

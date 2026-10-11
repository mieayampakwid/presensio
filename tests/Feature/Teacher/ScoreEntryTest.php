<?php

namespace Tests\Feature\Teacher;

use App\Enums\AssessmentKind;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Models\CourseSemester;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Score;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ScoreEntryTest extends TestCase
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

    private Assessment $assessment;

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

        $this->assessment = Assessment::factory()->create([
            'class_subject_id' => $this->classSubject->id,
            'semester_id' => $this->semester->id,
            'name' => 'Sumatif Lingkup Materi 1',
            'kind' => AssessmentKind::SummativeScope,
            'assessed_on' => '2026-08-15',
            'max_score' => '100.00',
        ]);

        $this->student = Student::factory()->create();
        Enrollment::create([
            'student_id' => $this->student->id,
            'class_id' => $this->schoolClass->id,
            'started_on' => '2026-07-01',
            'ended_on' => null,
        ]);
    }

    public function test_ac_10_01_saving_score_outside_max_score_returns_422(): void
    {
        // AC-10-01: Saving a score of 105 or -5 on max_score = 100 returns HTTP 422
        $response1 = $this->actingAs($this->teacherUser)
            ->postJson(route('courses.scores.store', $this->classSubject), [
                'scores' => [
                    [
                        'assessment_id' => $this->assessment->id,
                        'student_id' => $this->student->id,
                        'score' => 105,
                    ],
                ],
            ]);

        $response1->assertStatus(422);

        $response2 = $this->actingAs($this->teacherUser)
            ->postJson(route('courses.scores.store', $this->classSubject), [
                'scores' => [
                    [
                        'assessment_id' => $this->assessment->id,
                        'student_id' => $this->student->id,
                        'score' => -5,
                    ],
                ],
            ]);

        $response2->assertStatus(422);
    }

    public function test_remedial_score_cannot_be_set_without_main_score_returns_422(): void
    {
        $response = $this->actingAs($this->teacherUser)
            ->postJson(route('courses.scores.store', $this->classSubject), [
                'scores' => [
                    [
                        'assessment_id' => $this->assessment->id,
                        'student_id' => $this->student->id,
                        'score' => null,
                        'remedial_score' => 80,
                    ],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_cannot_save_score_for_ineligible_student_returns_422(): void
    {
        // Ineligible student (transferred in after assessed_on date)
        $lateStudent = Student::factory()->create();
        Enrollment::create([
            'student_id' => $lateStudent->id,
            'class_id' => $this->schoolClass->id,
            'started_on' => '2026-09-01', // After assessment on 2026-08-15
            'ended_on' => null,
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->postJson(route('courses.scores.store', $this->classSubject), [
                'scores' => [
                    [
                        'assessment_id' => $this->assessment->id,
                        'student_id' => $lateStudent->id,
                        'score' => 80,
                    ],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_teacher_can_batch_save_scores_and_records_audit_log_ac_10_14(): void
    {
        // First entry: create score
        $response = $this->actingAs($this->teacherUser)
            ->postJson(route('courses.scores.store', $this->classSubject), [
                'scores' => [
                    [
                        'assessment_id' => $this->assessment->id,
                        'student_id' => $this->student->id,
                        'score' => 60,
                        'remedial_score' => 75,
                        'notes' => 'Bagus setelah remedial',
                    ],
                ],
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('scores', [
            'assessment_id' => $this->assessment->id,
            'student_id' => $this->student->id,
            'score' => '60.00',
            'remedial_score' => '75.00',
            'notes' => 'Bagus setelah remedial',
            'graded_by_user_id' => $this->teacherUser->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'score_recorded',
            'auditable_type' => Score::class,
        ]);

        // Second entry: update score
        $responseUpdate = $this->actingAs($this->teacherUser)
            ->postJson(route('courses.scores.store', $this->classSubject), [
                'scores' => [
                    [
                        'assessment_id' => $this->assessment->id,
                        'student_id' => $this->student->id,
                        'score' => 65,
                        'remedial_score' => 80,
                        'notes' => 'Catatan diperbarui',
                    ],
                ],
            ]);

        $responseUpdate->assertOk();
        $this->assertDatabaseHas('scores', [
            'assessment_id' => $this->assessment->id,
            'student_id' => $this->student->id,
            'score' => '65.00',
            'remedial_score' => '80.00',
        ]);

        // AC-10-14: Audit log has old and new values
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'score_updated',
            'auditable_type' => Score::class,
        ]);
    }

    public function test_ac_15_10_score_recording_atomicity_rolls_back_if_audit_fails(): void
    {
        // Mock AuditLogger to throw exception
        $mockAudit = Mockery::mock(AuditLogger::class);
        $mockAudit->shouldReceive('record')
            ->andThrow(new Exception('Simulated audit failure'));
        $this->app->instance(AuditLogger::class, $mockAudit);

        try {
            $this->actingAs($this->teacherUser)
                ->postJson(route('courses.scores.store', $this->classSubject), [
                    'scores' => [
                        [
                            'assessment_id' => $this->assessment->id,
                            'student_id' => $this->student->id,
                            'score' => 90,
                        ],
                    ],
                ]);
        } catch (Exception $e) {
            // expected
        }

        // Score must NOT be persisted in DB
        $this->assertDatabaseMissing('scores', [
            'assessment_id' => $this->assessment->id,
            'student_id' => $this->student->id,
        ]);
    }

    public function test_teacher_can_release_assessment_idempotently(): void
    {
        $this->assertNull($this->assessment->released_at);

        $response = $this->actingAs($this->teacherUser)
            ->post(route('courses.assessments.release', [
                'class_subject' => $this->classSubject->id,
                'assessment' => $this->assessment->id,
            ]));

        $response->assertRedirect();
        $this->assertNotNull($this->assessment->fresh()->released_at);

        $firstReleasedAt = $this->assessment->fresh()->released_at;

        // Idempotent: second call keeps released_at
        $this->actingAs($this->teacherUser)
            ->post(route('courses.assessments.release', [
                'class_subject' => $this->classSubject->id,
                'assessment' => $this->assessment->id,
            ]));

        $this->assertEquals($firstReleasedAt, $this->assessment->fresh()->released_at);
    }

    public function test_teacher_can_update_course_weights(): void
    {
        $response = $this->actingAs($this->teacherUser)
            ->putJson(route('courses.weights.update', $this->classSubject), [
                'semester_id' => $this->semester->id,
                'scope_weight' => 60,
                'final_weight' => 40,
            ]);

        $response->assertOk();

        $courseSemester = CourseSemester::forCourse($this->classSubject, $this->semester);
        $this->assertEquals('60.00', $courseSemester->scope_weight);
        $this->assertEquals('40.00', $courseSemester->final_weight);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'weights_updated',
            'auditable_type' => CourseSemester::class,
        ]);

        // Weights sum must be > 0
        $invalidResponse = $this->actingAs($this->teacherUser)
            ->putJson(route('courses.weights.update', $this->classSubject), [
                'semester_id' => $this->semester->id,
                'scope_weight' => 0,
                'final_weight' => 0,
            ]);

        $invalidResponse->assertStatus(422);
    }

    public function test_cannot_mutate_scores_in_finalized_course_semester(): void
    {
        $courseSemester = CourseSemester::forCourse($this->classSubject, $this->semester);
        $courseSemester->update([
            'finalized_at' => now(),
            'finalized_by_user_id' => $this->teacherUser->id,
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->postJson(route('courses.scores.store', $this->classSubject), [
                'scores' => [
                    [
                        'assessment_id' => $this->assessment->id,
                        'student_id' => $this->student->id,
                        'score' => 85,
                    ],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_unassigned_teacher_cannot_enter_scores_returns_403(): void
    {
        $response = $this->actingAs($this->otherTeacherUser)
            ->postJson(route('courses.scores.store', $this->classSubject), [
                'scores' => [
                    [
                        'assessment_id' => $this->assessment->id,
                        'student_id' => $this->student->id,
                        'score' => 85,
                    ],
                ],
            ]);

        $response->assertForbidden();
    }
}

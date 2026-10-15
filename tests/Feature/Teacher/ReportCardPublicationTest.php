<?php

namespace Tests\Feature\Teacher;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\CourseSemester;
use App\Models\Enrollment;
use App\Models\ReportCard;
use App\Models\ReportCardPublication;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectResult;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ReportCards\ReportCardPublisher;
use App\Services\SchoolSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ReportCardPublicationTest extends TestCase
{
    use RefreshDatabase;

    private User $homeroomUser;

    private User $subjectTeacherUser;

    private User $adminUser;

    private User $principalUser;

    private AcademicYear $academicYear;

    private Semester $semester;

    private Semester $semester2;

    private SchoolClass $schoolClass;

    private ClassSubject $mathCourse;

    private ClassSubject $ipasCourse;

    private Student $budi;

    private Student $siti;

    protected function setUp(): void
    {
        parent::setUp();

        $this->academicYear = AcademicYear::factory()->create([
            'name' => '2026/2027',
            'starts_at' => '2026-07-01',
            'ends_at' => '2027-06-30',
            'is_active' => true,
        ]);
        $this->semester = $this->academicYear->semesters()->where('number', 1)->firstOrFail();
        $this->semester2 = $this->academicYear->semesters()->where('number', 2)->firstOrFail();

        $this->homeroomUser = User::factory()->teacher()->create();
        $this->subjectTeacherUser = User::factory()->teacher()->create();
        $this->adminUser = User::factory()->admin()->create();
        $this->principalUser = User::factory()->principal()->create();

        $homeroomTeacher = Teacher::factory()->create(['user_id' => $this->homeroomUser->id, 'name' => 'Pak Dedi']);
        $subjectTeacher = Teacher::factory()->create(['user_id' => $this->subjectTeacherUser->id, 'name' => 'Pak Guru']);

        $this->schoolClass = SchoolClass::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level' => 5,
            'name' => '5A',
            'teacher_id' => $homeroomTeacher->id,
        ]);

        $this->mathCourse = ClassSubject::factory()->create([
            'class_id' => $this->schoolClass->id,
            'subject_id' => Subject::factory()->create(['name' => 'Matematika', 'sort_order' => 1])->id,
            'teacher_id' => $subjectTeacher->id,
            'passing_threshold' => '75.00',
        ]);
        $this->ipasCourse = ClassSubject::factory()->create([
            'class_id' => $this->schoolClass->id,
            'subject_id' => Subject::factory()->create(['name' => 'IPAS', 'sort_order' => 2])->id,
            'teacher_id' => $homeroomTeacher->id,
            'passing_threshold' => '75.00',
        ]);

        $this->budi = Student::factory()->create(['full_name' => 'Budi Santoso']);
        $this->siti = Student::factory()->create(['full_name' => 'Siti Aminah']);

        foreach ([$this->budi, $this->siti] as $student) {
            Enrollment::create([
                'student_id' => $student->id,
                'class_id' => $this->schoolClass->id,
                'started_on' => '2026-07-01',
                'ended_on' => null,
            ]);
        }

        // A fully finalized, fully scored class: the happy-path baseline
        // (both semesters, so Genap tests only vary decisions).
        foreach ([$this->semester, $this->semester2] as $semester) {
            $this->finalizeCourse($this->mathCourse, $semester);
            $this->finalizeCourse($this->ipasCourse, $semester);
            foreach ([$this->budi, $this->siti] as $index => $student) {
                $score = 80 + $index;
                $this->giveResult($this->mathCourse, $student, $score, $semester);
                $this->giveResult($this->ipasCourse, $student, $score + 1, $semester);
            }
        }
    }

    public function test_publish_reports_unfinalized_subject_and_teacher(): void
    {
        // Replace the finalized math course with an unfinalized one.
        CourseSemester::forCourse($this->mathCourse, $this->semester)->update(['finalized_at' => null, 'finalized_by_user_id' => null]);

        $res = $this->publish($this->homeroomUser, $this->semester);

        $res->assertStatus(422);
        $res->assertJsonPath('errors.unfinalized_subjects.0.subject', 'Matematika');
        $res->assertJsonPath('errors.unfinalized_subjects.0.teacher', 'Pak Guru');
        $this->assertDatabaseCount('report_card_publications', 0);
        $this->assertDatabaseCount('report_cards', 0);
    }

    public function test_publish_blocks_when_finalized_course_lacks_student_result(): void
    {
        SubjectResult::query()->where('student_id', $this->siti->id)->delete();

        $res = $this->publish($this->homeroomUser, $this->semester);

        $res->assertStatus(422);
        $res->assertJsonPath('errors.missing_results.0.student', 'Siti Aminah');
        $this->assertDatabaseCount('report_card_publications', 0);
    }

    public function test_publish_genap_requires_decision_naming_student(): void
    {
        $res = $this->publish($this->homeroomUser, $this->semester2);

        $res->assertStatus(422);
        $res->assertJsonPath('errors.missing_decisions.0.student', 'Budi Santoso');
        $this->assertDatabaseCount('report_card_publications', 0);
    }

    public function test_publish_creates_versioned_snapshots_and_warns_on_missing_notes(): void
    {
        $res = $this->publish($this->homeroomUser, $this->semester);

        $res->assertOk();
        $res->assertJsonPath('version', 1);
        $this->assertCount(2, $res->json('warnings'));

        $this->assertDatabaseHas('report_card_publications', [
            'semester_id' => $this->semester->id,
            'class_id' => $this->schoolClass->id,
            'version' => 1,
            'published_by_user_id' => $this->homeroomUser->id,
            'retracted_at' => null,
        ]);
        $this->assertDatabaseCount('report_cards', 2);

        $publication = ReportCardPublication::query()->firstOrFail();
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $publication->getMorphClass(),
            'auditable_id' => $publication->id,
            'action' => 'published',
        ]);

        $payloads = ReportCard::query()->pluck('payload', 'student_id');
        $this->assertSame(80, $payloads[$this->budi->id]['subjects'][0]['final_score']);
        $this->assertSame('5A', $payloads[$this->budi->id]['class']['name']);
    }

    public function test_publish_rejected_while_a_version_is_current(): void
    {
        $this->publish($this->homeroomUser, $this->semester);

        $res = $this->publish($this->homeroomUser, $this->semester);

        $res->assertStatus(422);
        $this->assertDatabaseCount('report_card_publications', 1);
    }

    public function test_retract_requires_reason(): void
    {
        $this->publish($this->homeroomUser, $this->semester);

        $res = $this->actingAs($this->homeroomUser)
            ->postJson(route('classes.report-cards.retract', $this->schoolClass), [
                'semester_id' => $this->semester->id,
            ]);

        $res->assertStatus(422);
        $this->assertNull(ReportCardPublication::query()->firstOrFail()->retracted_at);
    }

    public function test_retract_sets_reason_audits_and_allows_republish_as_version_two(): void
    {
        $this->publish($this->homeroomUser, $this->semester);

        $res = $this->actingAs($this->homeroomUser)
            ->postJson(route('classes.report-cards.retract', $this->schoolClass), [
                'semester_id' => $this->semester->id,
                'reason' => 'Nilai matematika salah',
            ]);

        $res->assertOk();
        $publication = ReportCardPublication::query()->firstOrFail();
        $this->assertNotNull($publication->retracted_at);
        $this->assertSame($this->homeroomUser->id, $publication->retracted_by_user_id);
        $this->assertSame('Nilai matematika salah', $publication->retract_reason);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $publication->getMorphClass(),
            'auditable_id' => $publication->id,
            'action' => 'retracted',
        ]);

        $republish = $this->publish($this->homeroomUser, $this->semester);
        $republish->assertOk();
        $republish->assertJsonPath('version', 2);

        $this->assertSame(2, ReportCardPublication::query()->count());
        $this->assertSame(
            1,
            ReportCardPublication::query()->whereNull('retracted_at')->count(),
        );
        $this->assertDatabaseCount('report_cards', 4);
    }

    public function test_retracted_version_snapshot_stays_immutable_after_republish(): void
    {
        $this->publish($this->homeroomUser, $this->semester);
        $this->retract($this->homeroomUser);

        // Live data changes between versions.
        CourseSemester::forCourse($this->mathCourse, $this->semester)
            ->subjectResults()
            ->where('student_id', $this->siti->id)
            ->update(['final_score' => 95]);

        $this->publish($this->homeroomUser, $this->semester);

        $payloads = ReportCard::query()->get()->groupBy('student_id');
        $v1Scores = $payloads[$this->siti->id]
            ->sortBy('report_card_publication_id')
            ->pluck('payload')
            ->map(fn (array $payload) => $payload['subjects'][0]['final_score'])
            ->values();
        $this->assertSame([81, 95], $v1Scores->all());
    }

    public function test_published_snapshot_survives_student_and_principal_changes(): void
    {
        $this->publish($this->homeroomUser, $this->semester);

        $payloadBefore = ReportCard::query()->where('student_id', $this->budi->id)->firstOrFail()->payload;

        $this->budi->update(['address' => 'Jl. Baru 2']);
        app(SchoolSettings::class)->row()->update(['principal_name' => 'Bpk. Baru']);

        $payloadAfter = ReportCard::query()->where('student_id', $this->budi->id)->firstOrFail()->payload;

        $this->assertSame($payloadBefore, $payloadAfter);
        $this->assertNotSame('Bpk. Baru', $payloadAfter['principal']['name']);
    }

    public function test_principal_and_non_homeroom_teacher_cannot_publish_or_retract(): void
    {
        foreach ([$this->principalUser, $this->subjectTeacherUser] as $actor) {
            $this->publish($actor, $this->semester)->assertStatus(403);

            $this->publish($this->homeroomUser, $this->semester);

            $this->retract($actor)->assertStatus(403);

            // Reset for the next actor.
            ReportCardPublication::query()->forceDelete();
        }

        $this->assertDatabaseCount('report_card_publications', 0);
    }

    public function test_publish_rejects_semester_outside_class_year(): void
    {
        $otherYear = AcademicYear::factory()->create([
            'name' => '2025/2026',
            'starts_at' => '2025-07-01',
            'ends_at' => '2026-06-30',
        ]);
        $otherSemester = $otherYear->semesters()->where('number', 1)->firstOrFail();

        $res = $this->publish($this->homeroomUser, $otherSemester);

        $res->assertStatus(422);
        $this->assertDatabaseCount('report_card_publications', 0);
    }

    public function test_publication_timestamps_stored_as_utc_instant(): void
    {
        // App runs UTC; school is Asia/Jakarta (+07). 18:30 UTC = 01:30 next
        // day locally: stored instants stay UTC, displayed issue dates follow
        // the school calendar.
        Date::setTestNow('2026-12-20 18:30:00');

        try {
            $this->publish($this->homeroomUser, $this->semester);

            $publication = ReportCardPublication::query()->firstOrFail();
            $this->assertSame(
                '2026-12-20 18:30:00',
                $publication->published_at->format('Y-m-d H:i:s'),
            );

            $payload = ReportCard::query()->where('student_id', $this->budi->id)->firstOrFail()->payload;
            $this->assertSame('2026-12-21', $payload['published_at']);

            $summary = app(ReportCardPublisher::class)->currentPublication($this->schoolClass, $this->semester);
            $this->assertSame('2026-12-21', $summary['published_at']);

            $this->retract($this->homeroomUser);

            $publication->refresh();
            $this->assertSame(
                '2026-12-20 18:30:00',
                $publication->retracted_at->format('Y-m-d H:i:s'),
            );
        } finally {
            Date::setTestNow();
        }
    }

    private function publish(User $user, Semester $semester): TestResponse
    {
        return $this->actingAs($user)
            ->postJson(route('classes.report-cards.publish', $this->schoolClass), [
                'semester_id' => $semester->id,
            ]);
    }

    private function retract(User $user): TestResponse
    {
        return $this->actingAs($user)
            ->postJson(route('classes.report-cards.retract', $this->schoolClass), [
                'semester_id' => $this->semester->id,
                'reason' => 'Ditarik',
            ]);
    }

    /**
     * Finalize the course in the given semester.
     */
    private function finalizeCourse(ClassSubject $course, Semester $semester): void
    {
        CourseSemester::forCourse($course, $semester)->update([
            'finalized_at' => now(),
            'finalized_by_user_id' => $this->homeroomUser->id,
        ]);
    }

    /**
     * Freeze a finalized subject result for the student in the course.
     */
    private function giveResult(ClassSubject $course, Student $student, int $score, Semester $semester): void
    {
        CourseSemester::forCourse($course, $semester)->subjectResults()->create([
            'student_id' => $student->id,
            'final_score' => $score,
            'description' => 'Baik',
        ]);
    }
}

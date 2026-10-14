<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationType;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\CourseSemester;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\ReportCardPublication;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Notifications\ReportCardNotifier;
use App\Services\ReportCards\ReportCardPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReportCardNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $homeroomUser;

    private AcademicYear $academicYear;

    private Semester $semester;

    private SchoolClass $schoolClass;

    private Student $budi;

    private Guardian $guardian;

    private User $guardianUser;

    private User $studentUser;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->academicYear = AcademicYear::factory()->create([
            'name' => '2026/2027',
            'starts_at' => '2026-07-01',
            'ends_at' => '2027-06-30',
            'is_active' => true,
        ]);
        $this->semester = $this->academicYear->semesters()->where('number', 1)->firstOrFail();

        $this->homeroomUser = User::factory()->teacher()->create();

        $this->schoolClass = SchoolClass::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level' => 5,
            'name' => '5A',
            'teacher_id' => Teacher::factory()->create(['user_id' => $this->homeroomUser->id])->id,
        ]);

        $this->studentUser = User::factory()->student()->create(['username' => 'budi']);

        $this->budi = Student::factory()->create([
            'user_id' => $this->studentUser->id,
            'full_name' => 'Budi Santoso',
        ]);

        Enrollment::create([
            'student_id' => $this->budi->id,
            'class_id' => $this->schoolClass->id,
            'started_on' => '2026-07-01',
            'ended_on' => null,
        ]);

        $this->guardianUser = User::factory()->parent()->create();
        $this->guardian = Guardian::factory()->create(['user_id' => $this->guardianUser->id]);
        $this->guardian->students()->attach($this->budi->id, ['relationship_type' => 'father']);

        $mathCourse = ClassSubject::factory()->create([
            'class_id' => $this->schoolClass->id,
            'subject_id' => Subject::factory()->create(['name' => 'Matematika'])->id,
            'teacher_id' => $this->schoolClass->teacher_id,
            'passing_threshold' => '75.00',
        ]);
        CourseSemester::forCourse($mathCourse, $this->semester)->update([
            'finalized_at' => now(),
            'finalized_by_user_id' => $this->homeroomUser->id,
        ]);
        CourseSemester::forCourse($mathCourse, $this->semester)->subjectResults()->create([
            'student_id' => $this->budi->id,
            'final_score' => 97,
            'description' => 'Sangat baik',
        ]);
    }

    public function test_publishing_notifies_guardian_and_student_with_dedupe_base(): void
    {
        $publication = $this->publish();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->guardianUser->id,
            'dedupe_key' => "report_card_published:publication:{$publication->id}:guardian:{$this->guardian->id}",
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->studentUser->id,
            'dedupe_key' => "report_card_published:publication:{$publication->id}:user:{$this->studentUser->id}",
        ]);

        // Guardians may also receive WhatsApp, keyed on the same base.
        $this->assertDatabaseHas('notification_deliveries', [
            'dedupe_key' => "report_card_published:publication:{$publication->id}:guardian:{$this->guardian->id}",
            'type_key' => NotificationType::ReportCardPublished->value,
            'recipient_type' => 'guardian',
            'channel' => 'whatsapp',
        ]);
    }

    public function test_publish_notification_payload_contains_no_scores(): void
    {
        $this->publish();

        $notification = $this->guardianUser->notifications()->firstOrFail();
        $serialized = json_encode($notification->data);

        $this->assertStringNotContainsString('final_score', $serialized);
        $this->assertStringNotContainsString('97', (string) $notification->data['body']);
        $this->assertStringContainsString('Budi Santoso', (string) $notification->data['body']);
    }

    public function test_re_dispatching_the_same_publication_creates_no_duplicates(): void
    {
        $publication = $this->publish();

        // A second dispatch of the same publication event is an idempotent no-op.
        app(ReportCardNotifier::class)->published($publication);

        $this->assertSame(1, $this->guardianUser->notifications()->count());
        $this->assertSame(1, $this->studentUser->notifications()->count());
    }

    public function test_retracting_notifies_with_retracted_type_and_dedupe_base(): void
    {
        $publication = $this->publish();
        $this->retract($publication);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->guardianUser->id,
            'dedupe_key' => "report_card_retracted:publication:{$publication->id}:retracted:guardian:{$this->guardian->id}",
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->studentUser->id,
            'dedupe_key' => "report_card_retracted:publication:{$publication->id}:retracted:user:{$this->studentUser->id}",
        ]);

        // Retraction is in-app only, never WhatsApp.
        $this->assertDatabaseMissing('notification_deliveries', [
            'type_key' => NotificationType::ReportCardRetracted->value,
            'channel' => 'whatsapp',
        ]);

        // Re-dispatching the retraction stays idempotent.
        app(ReportCardNotifier::class)->retracted($publication->refresh());
        $this->assertSame(
            1,
            $this->guardianUser->notifications()
                ->where('dedupe_key', "report_card_retracted:publication:{$publication->id}:retracted:guardian:{$this->guardian->id}")
                ->count(),
        );
    }

    public function test_republished_version_notifies_again_as_a_new_publication(): void
    {
        $v1 = $this->publish();
        $this->retract($v1);

        $this->actingAs($this->homeroomUser);
        app(ReportCardPublisher::class)->publish($this->schoolClass, $this->semester);
        $v2 = ReportCardPublication::query()->where('version', 2)->firstOrFail();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->guardianUser->id,
            'dedupe_key' => "report_card_published:publication:{$v2->id}:guardian:{$this->guardian->id}",
        ]);

        $this->assertSame(3, $this->guardianUser->notifications()->count());
    }

    public function test_recipients_without_accounts_or_phones_are_skipped(): void
    {
        // A guardian without a user account: no in-app notification, but the
        // unique phone still reaches them on WhatsApp.
        $offlineGuardian = Guardian::factory()->create(['user_id' => null]);
        $offlineGuardian->students()->attach($this->budi->id, ['relationship_type' => 'mother']);

        // A student without a user account receives no student notification.
        $unlinked = Student::factory()->create(['user_id' => null, 'full_name' => 'Tanpa Akun']);
        Enrollment::create([
            'student_id' => $unlinked->id,
            'class_id' => $this->schoolClass->id,
            'started_on' => '2026-07-01',
            'ended_on' => null,
        ]);
        CourseSemester::query()->firstOrFail()->subjectResults()->create([
            'student_id' => $unlinked->id,
            'final_score' => 80,
            'description' => 'Baik',
        ]);

        $publication = $this->publish();

        // Linked account holders are notified in-app.
        $this->assertSame(1, $this->guardianUser->notifications()->count());
        $this->assertSame(1, $this->studentUser->notifications()->count());

        // The account-less guardian is only reachable externally.
        $this->assertDatabaseHas('notification_deliveries', [
            'dedupe_key' => "report_card_published:publication:{$publication->id}:guardian:{$offlineGuardian->id}",
            'channel' => 'whatsapp',
        ]);

        // No student notification exists for the account-less student: only
        // the linked student user got one.
        $this->assertSame(1, DB::table('notifications')->where('dedupe_key', 'like', '%:user:%')->count());
    }

    /**
     * Publish the semester as the homeroom teacher.
     */
    private function publish(): ReportCardPublication
    {
        $this->actingAs($this->homeroomUser);
        app(ReportCardPublisher::class)->publish($this->schoolClass, $this->semester);

        return ReportCardPublication::query()->current()->firstOrFail();
    }

    private function retract(ReportCardPublication $publication): void
    {
        $this->actingAs($this->homeroomUser);
        app(ReportCardPublisher::class)->retract($this->schoolClass, $this->semester, 'Perbaikan nilai');
    }
}

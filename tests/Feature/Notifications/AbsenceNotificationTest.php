<?php

namespace Tests\Feature\Notifications;

use App\Enums\AttendanceStatus;
use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Jobs\SendAbsenceNotifications;
use App\Mail\AbsenceAlertMail;
use App\Models\AbsenceNotification;
use App\Models\Attendance;
use App\Models\Excuse;
use App\Models\Guardian;
use App\Models\RfidCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Notifications\WhatsAppClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AbsenceNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-scanner-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['attendance.scanner_keys' => [self::KEY]]);
        config(['services.waha.base_url' => 'http://waha.test']);
        // Jakarta is already past midnight on Monday the 21st while UTC
        // still says Sunday the 20th — pins the school-tz default date.
        Date::setTestNow('2026-09-20 18:30:00', 'UTC');
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    private function teacherUser(): array
    {
        $user = User::factory()->teacher()->create();
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $class = SchoolClass::factory()->create(['teacher_id' => $teacher->id]);
        $student = Student::factory()->enrolledIn($class)->create();

        return [$user, $student];
    }

    private function absentAttendance(Student $student): Attendance
    {
        return Attendance::factory()->absent()->create([
            'student_id' => $student->id,
            'date' => '2026-09-21',
        ]);
    }

    public function test_schools_can_enable_more_statuses_via_config(): void
    {
        [$user, $student] = $this->teacherUser();
        config(['attendance.notifications.statuses' => ['absent', 'present', 'late']]);
        Queue::fake();

        $this->actingAs($user)
            ->put(route('attendance.record.update'), [
                'student_id' => $student->id,
                'date' => '2026-09-21',
                'status' => 'present',
            ])
            ->assertRedirect();

        Queue::assertPushed(SendAbsenceNotifications::class);
    }

    public function test_the_message_wording_follows_the_record_status(): void
    {
        [, $student] = $this->teacherUser();
        config(['attendance.notifications.statuses' => ['present']]);

        $guardianUser = User::factory()->parent()->create();
        $guardian = Guardian::factory()->create(['phone_number' => '', 'user_id' => $guardianUser->id]);
        $guardian->students()->attach($student->id);

        $attendance = Attendance::factory()->create([
            'student_id' => $student->id,
            'date' => '2026-09-21',
            'status' => AttendanceStatus::Present,
        ]);

        Mail::fake();
        Http::fake();

        (new SendAbsenceNotifications($attendance))->handle(new WhatsAppClient);

        Mail::assertSent(AbsenceAlertMail::class, function (AbsenceAlertMail $mail) use ($guardianUser, $student): bool {
            return $mail->hasTo($guardianUser->email)
                && $mail->statusLabel === 'hadir'
                && $mail->envelope()->subject === "Absensi: {$student->full_name} hadir 2026-09-21";
        });
        Http::assertNothingSent();
    }

    public function test_the_absence_sweep_dispatches_notifications(): void
    {
        Student::factory()->enrolledIn(SchoolClass::factory()->create())->create();
        Queue::fake();

        $this->artisan('attendance:mark-absences')->assertSuccessful();

        Queue::assertPushed(SendAbsenceNotifications::class);
    }

    public function test_manually_creating_an_absent_record_dispatches_notifications(): void
    {
        [$user, $student] = $this->teacherUser();
        Queue::fake();

        $this->actingAs($user)
            ->put(route('attendance.record.update'), [
                'student_id' => $student->id,
                'date' => '2026-09-21',
                'status' => 'absent',
            ])
            ->assertRedirect();

        Queue::assertPushed(SendAbsenceNotifications::class);
    }

    public function test_editing_an_existing_record_never_dispatches(): void
    {
        [$user, $student] = $this->teacherUser();
        Attendance::factory()->create([
            'student_id' => $student->id,
            'date' => '2026-09-21',
        ]);
        Queue::fake();

        $this->actingAs($user)
            ->put(route('attendance.record.update'), [
                'student_id' => $student->id,
                'date' => '2026-09-21',
                'status' => 'late',
                'checked_in_at' => '07:45',
            ])
            ->assertRedirect();

        Queue::assertNothingPushed();
    }

    #[DataProvider('nonAbsentWriters')]
    public function test_non_absent_record_creation_never_dispatches(string $writer): void
    {
        [$user, $student] = $this->teacherUser();

        if ($writer === 'excuse') {
            Excuse::factory()->create([
                'student_id' => $student->id,
                'start_date' => '2026-09-21',
                'end_date' => '2026-09-21',
            ]);
        }

        if ($writer === 'scan') {
            RfidCard::factory()->assigned($student)->create(['rfid_number' => '001234']);
        }

        Queue::fake();

        match ($writer) {
            'upsert' => $this->actingAs($user)
                ->put(route('attendance.record.update'), [
                    'student_id' => $student->id,
                    'date' => '2026-09-21',
                    'status' => 'present',
                ])->assertRedirect(),
            'bulk' => $this->actingAs($user)
                ->post(route('attendance.bulk-present'), [
                    'class_id' => $student->class_id,
                    'date' => '2026-09-21',
                ])->assertRedirect(),
            'excuse' => $this->actingAs(User::factory()->admin()->create())
                ->put(route('excuses.approve', ['excuse' => Excuse::query()->sole()->id]))
                ->assertRedirect(),
            'scan' => $this->postJson(route('attendance.scan'), [
                'credential_type' => 'rfid',
                'credential' => '001234',
            ], ['X-Scanner-Key' => self::KEY])->assertOk(),
        };

        Queue::assertNothingPushed();
    }

    public static function nonAbsentWriters(): array
    {
        return [
            ['upsert'],
            ['bulk'],
            ['excuse'],
            ['scan'],
        ];
    }

    public function test_sends_whatsapp_to_every_guardian_with_a_phone(): void
    {
        $student = Student::factory()->create();
        $first = Guardian::factory()->create(['phone_number' => '+628111111111']);
        $second = Guardian::factory()->create(['phone_number' => '082222222222']);
        $first->students()->attach($student->id);
        $second->students()->attach($student->id);
        $attendance = $this->absentAttendance($student);

        Http::fake(['*/api/sendText' => Http::response(['success' => true])]);

        (new SendAbsenceNotifications($attendance))->handle(new WhatsAppClient);

        Http::assertSentCount(2);

        $bodies = [];
        Http::assertSent(function (Request $request) use ($student, &$bodies): bool {
            $bodies[] = $request['chatId'];

            return $request['session'] === 'default'
                && str_contains($request['text'], $student->full_name)
                && str_contains($request['text'], 'TIDAK HADIR')
                && str_contains($request['text'], 'Senin, 21 September 2026')
                && str_contains($request['text'], (string) config('app.url'));
        });

        $this->assertSame(2, count($bodies));
        // Both normalized forms must have gone out: +62 stripped, local
        // 0-prefixed number upgraded to the 62 country code.
        $this->assertSame(
            ['628111111111@c.us', '6282222222222@c.us'],
            collect($bodies)->sort()->values()->all(),
        );

        $this->assertSame(2, AbsenceNotification::query()
            ->where('channel', NotificationChannel::WhatsApp->value)
            ->where('status', NotificationDeliveryStatus::Sent->value)
            ->count());
    }

    public function test_guardian_without_a_phone_falls_back_to_the_linked_users_email(): void
    {
        $student = Student::factory()->create();
        $user = User::factory()->parent()->create();
        // phone_number is a NOT NULL column — a "missing" phone is ''.
        $guardian = Guardian::factory()->create(['phone_number' => '', 'user_id' => $user->id]);
        $guardian->students()->attach($student->id);
        $attendance = $this->absentAttendance($student);

        Http::fake();
        Mail::fake();

        (new SendAbsenceNotifications($attendance))->handle(new WhatsAppClient);

        Mail::assertSent(AbsenceAlertMail::class, function (AbsenceAlertMail $mail) use ($user, $student): bool {
            return $mail->hasTo($user->email) && $mail->studentName === $student->full_name;
        });
        Http::assertNothingSent();

        $row = AbsenceNotification::query()->sole();
        $this->assertTrue($row->channel === NotificationChannel::Email);
        $this->assertTrue($row->status === NotificationDeliveryStatus::Sent);
    }

    public function test_guardian_without_phone_or_linked_user_is_silently_skipped(): void
    {
        $student = Student::factory()->create();
        $guardian = Guardian::factory()->create(['phone_number' => '', 'user_id' => null]);
        $guardian->students()->attach($student->id);
        $attendance = $this->absentAttendance($student);

        Http::fake();
        Mail::fake();

        (new SendAbsenceNotifications($attendance))->handle(new WhatsAppClient);

        Http::assertNothingSent();
        Mail::assertNothingSent();
        $this->assertSame(0, AbsenceNotification::count());
    }

    public function test_a_student_without_guardians_triggers_nothing(): void
    {
        $student = Student::factory()->create();
        $attendance = $this->absentAttendance($student);

        Http::fake();
        Mail::fake();

        (new SendAbsenceNotifications($attendance))->handle(new WhatsAppClient);

        Http::assertNothingSent();
        Mail::assertNothingSent();
        $this->assertSame(0, AbsenceNotification::count());
    }

    public function test_an_already_sent_row_is_never_resent(): void
    {
        $student = Student::factory()->create();
        $guardian = Guardian::factory()->create();
        $guardian->students()->attach($student->id);
        $attendance = $this->absentAttendance($student);

        AbsenceNotification::create([
            'attendance_id' => $attendance->id,
            'guardian_id' => $guardian->id,
            'channel' => NotificationChannel::WhatsApp,
            'status' => NotificationDeliveryStatus::Sent,
        ]);

        Http::fake();

        (new SendAbsenceNotifications($attendance))->handle(new WhatsAppClient);

        Http::assertNothingSent();
        $this->assertSame(1, AbsenceNotification::count());
    }

    public function test_a_waha_failure_marks_the_row_failed_and_throws_for_a_retry(): void
    {
        $student = Student::factory()->create();
        $guardian = Guardian::factory()->create();
        $guardian->students()->attach($student->id);
        $attendance = $this->absentAttendance($student);

        Http::fake(['*/api/sendText' => Http::response(['success' => false], 500)]);

        try {
            (new SendAbsenceNotifications($attendance))->handle(new WhatsAppClient);
            $this->fail('Expected the job to throw so the queue retries.');
        } catch (RuntimeException) {
        }

        $row = AbsenceNotification::query()->sole();
        $this->assertTrue($row->channel === NotificationChannel::WhatsApp);
        $this->assertTrue($row->status === NotificationDeliveryStatus::Failed);
    }

    public function test_failed_hook_falls_back_to_email_for_failed_whatsapp_rows(): void
    {
        $student = Student::factory()->create();
        $user = User::factory()->parent()->create();
        $guardian = Guardian::factory()->create(['user_id' => $user->id]);
        $guardian->students()->attach($student->id);
        $attendance = $this->absentAttendance($student);

        $row = AbsenceNotification::create([
            'attendance_id' => $attendance->id,
            'guardian_id' => $guardian->id,
            'channel' => NotificationChannel::WhatsApp,
            'status' => NotificationDeliveryStatus::Failed,
        ]);

        Mail::fake();

        (new SendAbsenceNotifications($attendance))->failed(new RuntimeException('attempts exhausted'));

        Mail::assertSent(AbsenceAlertMail::class, fn (AbsenceAlertMail $mail) => $mail->hasTo($user->email));

        $row->refresh();
        $this->assertTrue($row->channel === NotificationChannel::Email);
        $this->assertTrue($row->status === NotificationDeliveryStatus::Sent);
    }

    public function test_a_record_corrected_before_delivery_suppresses_the_alert(): void
    {
        $student = Student::factory()->create();
        $guardian = Guardian::factory()->create();
        $guardian->students()->attach($student->id);
        $attendance = $this->absentAttendance($student);

        // Corrected between dispatch and delivery — the job's in-memory
        // snapshot still says absent, the database no longer does.
        Attendance::query()->update(['status' => 'sick']);

        Http::fake();
        Mail::fake();

        (new SendAbsenceNotifications($attendance))->handle(new WhatsAppClient);

        Http::assertNothingSent();
        Mail::assertNothingSent();
        $this->assertSame(0, AbsenceNotification::count());
    }
}

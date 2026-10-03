<?php

namespace Tests\Feature\Notifications;

use App\Enums\AttendanceStatus;
use App\Jobs\SendAbsenceNotifications;
use App\Models\Attendance;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Services\Notifications\WhatsAppClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AbsenceCopyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.waha.base_url' => 'https://waha.test']);
    }

    public function test_ac_17_08_absence_alert_writes_in_app_notification_for_guardian_with_account(): void
    {
        Http::fake();

        $user = User::factory()->parent()->create();
        $guardian = Guardian::factory()->create(['user_id' => $user->id, 'phone_number' => '08123456789']);
        $student = Student::factory()->create();
        $guardian->students()->attach($student->id, ['relationship_type' => 'mother']);

        $attendance = Attendance::factory()->create([
            'student_id' => $student->id,
            'status' => AttendanceStatus::Absent,
            'date' => '2026-10-05',
        ]);

        $job = new SendAbsenceNotifications($attendance);
        $job->handle(app(WhatsAppClient::class));

        // In-app notification was written with specified key format
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'notifiable_type' => User::class,
            'dedupe_key' => "absence_alert:{$attendance->id}:{$user->id}",
        ]);

        $notification = $user->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame('Pemberitahuan Ketidakhadiran', $notification->data['title']);

        // Spec 05 ledger rows unchanged
        $this->assertDatabaseHas('absence_notifications', [
            'attendance_id' => $attendance->id,
            'guardian_id' => $guardian->id,
        ]);
    }
}

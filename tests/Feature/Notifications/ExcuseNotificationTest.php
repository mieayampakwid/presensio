<?php

namespace Tests\Feature\Notifications;

use App\Enums\ExcuseStatus;
use App\Enums\ExcuseType;
use App\Models\Enrollment;
use App\Models\Excuse;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Attendance\ExcuseApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExcuseNotificationTest extends TestCase
{
    use RefreshDatabase;

    private ExcuseApprovalService $approvalService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->approvalService = app(ExcuseApprovalService::class);
        Storage::fake('local');
    }

    public function test_submitting_excuse_creates_in_app_notification_for_admins_and_homeroom(): void
    {
        $admin = User::factory()->admin()->create();
        $teacherUser = User::factory()->teacher()->create();
        $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);
        $schoolClass = SchoolClass::factory()->create(['teacher_id' => $teacher->id]);

        $parentUser = User::factory()->parent()->create();
        $guardian = Guardian::factory()->create(['user_id' => $parentUser->id]);
        $student = Student::factory()->create();
        $guardian->students()->attach($student->id, ['relationship_type' => 'mother']);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'class_id' => $schoolClass->id,
            'started_on' => '2026-07-01',
            'ended_on' => null,
        ]);

        $payload = [
            'student_id' => $student->id,
            'type' => ExcuseType::Sick->value,
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-06',
            'reason' => 'Demam tinggi dan istirahat dokter',
            'attachment' => UploadedFile::fake()->create('surat.pdf', 100, 'application/pdf'),
        ];

        $response = $this->actingAs($parentUser)->post('/excuses', $payload);
        $response->assertRedirect('/my-excuses');

        // Admin received in-app notification
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $admin->id,
            'notifiable_type' => User::class,
        ]);
        $adminNotif = $admin->notifications()->first();
        $this->assertNotNull($adminNotif);
        $this->assertSame('Pengajuan Izin Baru', $adminNotif->data['title']);

        // Homeroom teacher received in-app notification
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $teacherUser->id,
            'notifiable_type' => User::class,
        ]);
        $teacherNotif = $teacherUser->notifications()->first();
        $this->assertNotNull($teacherNotif);
        $this->assertSame('Pengajuan Izin Baru', $teacherNotif->data['title']);
    }

    public function test_ac_17_01_approving_excuse_creates_in_app_notification_for_submitting_guardian(): void
    {
        $admin = User::factory()->admin()->create();
        $parentUser = User::factory()->parent()->create();
        $guardian = Guardian::factory()->create(['user_id' => $parentUser->id]);
        $student = Student::factory()->create();

        $excuse = Excuse::factory()->create([
            'student_id' => $student->id,
            'submitted_by_guardian_id' => $guardian->id,
            'status' => ExcuseStatus::Pending,
            'reason' => 'Sakit panas',
        ]);

        $this->approvalService->approve($excuse, $admin, 'Semoga lekas sembuh');

        // Submitting guardian received notification
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $parentUser->id,
            'notifiable_type' => User::class,
        ]);

        $notification = $parentUser->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame('Pengajuan Izin Disetujui', $notification->data['title']);
        $this->assertStringContainsString('my-excuses', $notification->data['url']);
        $this->assertSame(1, $parentUser->unreadNotifications()->count());

        // AC-17-10: Payload does NOT contain excuse reason or attachment
        $data = $notification->data;
        $this->assertArrayNotHasKey('reason', $data);
        $this->assertArrayNotHasKey('attachment', $data);
        $this->assertArrayNotHasKey('attachment_path', $data);
        $this->assertStringNotContainsString('Sakit panas', $data['body']);
    }

    public function test_rejecting_excuse_creates_in_app_notification_for_submitting_guardian(): void
    {
        $admin = User::factory()->admin()->create();
        $parentUser = User::factory()->parent()->create();
        $guardian = Guardian::factory()->create(['user_id' => $parentUser->id]);
        $student = Student::factory()->create();

        $excuse = Excuse::factory()->create([
            'student_id' => $student->id,
            'submitted_by_guardian_id' => $guardian->id,
            'status' => ExcuseStatus::Pending,
            'reason' => 'Keperluan keluarga',
        ]);

        $this->approvalService->reject($excuse, $admin, 'Surat tidak lengkap');

        $notification = $parentUser->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame('Pengajuan Izin Ditolak', $notification->data['title']);
        $this->assertStringContainsString('my-excuses', $notification->data['url']);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\ExcuseStatus;
use App\Enums\Gender;
use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\RelationshipType;
use App\Jobs\SendAbsenceNotifications;
use App\Models\AbsenceNotification;
use App\Models\Attendance;
use App\Models\Excuse;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Services\Attendance\ExcuseApprovalService;
use App\Services\Notifications\WhatsAppClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProfileSchemaSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_schema_end_to_end_smoke(): void
    {
        // 1. Student creation and enum casting
        $student = Student::create([
            'full_name' => 'Siti Rahma',
            'dob' => '2013-04-12',
            'gender' => Gender::Female,
            'birth_place' => 'Surabaya',
            'religion' => 'Islam',
            'address' => 'Jl. Pahlawan No. 45',
        ]);

        $this->assertSame(Gender::Female, $student->gender);
        $this->assertSame('Surabaya', $student->birth_place);
        $this->assertSame('Islam', $student->religion);
        $this->assertSame('Jl. Pahlawan No. 45', $student->address);

        // 2. Guardian with pivot relationship_type
        $guardian = Guardian::create([
            'name' => 'Hasan Basri',
            'phone_number' => '+628123456789',
        ]);

        $student->guardians()->attach($guardian->id, [
            'relationship_type' => RelationshipType::Father->value,
        ]);

        $linked = $student->fresh()->guardians->first();
        $this->assertSame('father', $linked->pivot->relationship_type);

        $guardianLinked = $guardian->fresh()->students->first();
        $this->assertSame('father', $guardianLinked->pivot->relationship_type);

        // 3. Excuse submission audit: submitted_by_guardian_id and reviewed_at
        $excuse = Excuse::create([
            'student_id' => $student->id,
            'type' => 'sick',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-02',
            'reason' => 'Demam tinggi',
            'status' => ExcuseStatus::Pending,
            'submitted_by_guardian_id' => $guardian->id,
        ]);

        $this->assertSame($guardian->id, $excuse->submitted_by_guardian_id);
        $this->assertSame($guardian->id, $excuse->submittedByGuardian->id);
        $this->assertNull($excuse->reviewed_at);

        $admin = User::factory()->admin()->create();
        $approvalService = app(ExcuseApprovalService::class);
        $approved = $approvalService->approve($excuse, $admin, 'Surat dokter terverifikasi');

        $this->assertTrue($approved);
        $excuse->refresh();
        $this->assertSame(ExcuseStatus::Approved, $excuse->status);
        $this->assertSame($admin->id, $excuse->reviewed_by_user_id);
        $this->assertNotNull($excuse->reviewed_at);

        // 4. Absence notification delivery audit
        config(['services.waha.base_url' => 'http://waha.test']);
        $attendance = Attendance::factory()->create([
            'student_id' => $student->id,
            'date' => '2026-10-05',
            'status' => AttendanceStatus::Absent,
        ]);

        Http::fake([
            '*/api/sendText' => Http::response(['id' => 'msg_waha_9999', 'success' => true]),
        ]);
        (new SendAbsenceNotifications($attendance))->handle(new WhatsAppClient);

        $notification = AbsenceNotification::where('attendance_id', $attendance->id)
            ->where('guardian_id', $guardian->id)
            ->firstOrFail();

        $this->assertSame(NotificationDeliveryStatus::Sent, $notification->status);
        $this->assertSame(NotificationChannel::WhatsApp, $notification->channel);
        $this->assertSame('+628123456789', $notification->recipient_contact);
        $this->assertSame('msg_waha_9999', $notification->provider_message_id);
        $this->assertNotNull($notification->sent_at);
        $this->assertNull($notification->error_message);
    }
}

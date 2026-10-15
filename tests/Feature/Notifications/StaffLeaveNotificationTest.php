<?php

namespace Tests\Feature\Notifications;

use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\EmployeeLeaveRequest;
use App\Models\User;
use App\Services\Attendance\LeaveApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffLeaveNotificationTest extends TestCase
{
    use RefreshDatabase;

    private LeaveApprovalService $approvalService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->approvalService = app(LeaveApprovalService::class);
    }

    /**
     * Any role works as long as the user has an employee profile (spec 15
     * note) — the default factory role is teacher.
     *
     * @return array{0: User, 1: Employee}
     */
    private function employeeUser(): array
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->forUser($user)->create();

        return [$user, $employee];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'sick',
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-05',
            'reason' => 'Demam, ada surat dokter.',
        ], $overrides);
    }

    public function test_submitting_leave_request_dispatches_in_app_notification_to_admins_and_principals(): void
    {
        $admin = User::factory()->admin()->create();
        $principal = User::factory()->principal()->create();
        [$user, $employee] = $this->employeeUser();

        $response = $this->actingAs($user)
            ->post(route('my-leave.store'), $this->payload());

        $response->assertRedirect(route('my-leave.index'));

        $leaveRequest = EmployeeLeaveRequest::query()->sole();

        foreach ([$admin, $principal] as $reviewer) {
            $notification = $reviewer->notifications()->first();
            $this->assertNotNull($notification);

            $this->assertSame('Pengajuan Cuti / Izin Baru', $notification->data['title']);
            $this->assertStringContainsString(
                "leave_request:{$leaveRequest->id}:submitted",
                $notification->data['key'],
            );
            $this->assertStringContainsString('staff-leave', $notification->data['url']);
        }

        // The submitting employee's user receives nothing on submission.
        $this->assertSame(0, $user->notifications()->count());
    }

    public function test_approving_leave_request_dispatches_in_app_notification_to_employee_user(): void
    {
        $admin = User::factory()->admin()->create();
        [$user, $employee] = $this->employeeUser();

        $leaveRequest = EmployeeLeaveRequest::factory()->create([
            'employee_id' => $employee->id,
            'status' => LeaveRequestStatus::Pending,
        ]);

        $this->approvalService->approve($leaveRequest, $admin, 'Setuju');

        $notification = $user->notifications()->first();
        $this->assertNotNull($notification);

        $this->assertSame('Pengajuan Cuti / Izin Disetujui', $notification->data['title']);
        $this->assertStringContainsString(
            "leave_request:{$leaveRequest->id}:reviewed",
            $notification->data['key'],
        );
        $this->assertStringContainsString('my-leave', $notification->data['url']);
    }

    public function test_rejecting_leave_request_dispatches_in_app_notification_to_employee_user(): void
    {
        $admin = User::factory()->admin()->create();
        [$user, $employee] = $this->employeeUser();

        $leaveRequest = EmployeeLeaveRequest::factory()->create([
            'employee_id' => $employee->id,
            'status' => LeaveRequestStatus::Pending,
        ]);

        $this->approvalService->reject($leaveRequest, $admin, 'Surat tidak lengkap');

        $notification = $user->notifications()->first();
        $this->assertNotNull($notification);

        $this->assertSame('Pengajuan Cuti / Izin Ditolak', $notification->data['title']);
        $this->assertStringContainsString(
            "leave_request:{$leaveRequest->id}:reviewed",
            $notification->data['key'],
        );
        $this->assertStringContainsString('my-leave', $notification->data['url']);
    }

    public function test_offline_employee_without_user_account_is_handled_gracefully(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create(['user_id' => null]);

        $leaveRequest = EmployeeLeaveRequest::factory()->create([
            'employee_id' => $employee->id,
            'status' => LeaveRequestStatus::Pending,
        ]);

        $this->assertTrue($this->approvalService->approve($leaveRequest, $admin, 'Setuju'));

        $this->assertSame(
            LeaveRequestStatus::Approved,
            $leaveRequest->fresh()->status,
        );
        $this->assertSame(0, $admin->notifications()->count());
    }

    public function test_payload_carries_no_reason_text(): void
    {
        $admin = User::factory()->admin()->create();
        [$user, $employee] = $this->employeeUser();

        $this->actingAs($user)
            ->post(route('my-leave.store'), $this->payload())
            ->assertRedirect(route('my-leave.index'));

        $notification = $admin->notifications()->first();
        $this->assertNotNull($notification);

        $data = $notification->data;
        $this->assertArrayNotHasKey('reason', $data);
        $this->assertArrayNotHasKey('attachment', $data);
        $this->assertArrayNotHasKey('attachment_path', $data);
        $this->assertStringNotContainsString('Demam', $data['body']);
    }
}

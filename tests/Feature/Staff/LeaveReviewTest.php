<?php

namespace Tests\Feature\Staff;

use App\Enums\EmployeeAttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveType;
use App\Enums\ScanMethod;
use App\Enums\ScanOutcome;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeLeaveRequest;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Services\Attendance\EmployeeScanService;
use App\Services\SchoolSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LeaveReviewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 2026-10-05 is a Monday; the school runs Mon–Fri in Asia/Jakarta.
     */
    protected function setUp(): void
    {
        parent::setUp();

        SchoolSetting::first()->update([
            'school_timezone' => 'Asia/Jakarta',
            'staff_start_time' => '07:00:00',
            'staff_end_time' => '14:00:00',
            'school_operational_days' => [1, 2, 3, 4, 5],
        ]);
        app(SchoolSettings::class)->refresh();
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    private function reviewer(string $role): User
    {
        return User::factory()->{$role}()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function pendingRequest(array $overrides = []): EmployeeLeaveRequest
    {
        return EmployeeLeaveRequest::factory()->create(array_merge([
            'type' => LeaveType::Sick,
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-05',
            'status' => LeaveRequestStatus::Pending,
        ], $overrides));
    }

    public function test_admin_and_principal_can_open_the_review_queue_which_defaults_to_pending(): void
    {
        $this->pendingRequest();
        $this->pendingRequest(['status' => LeaveRequestStatus::Approved]);

        foreach (['admin', 'principal'] as $role) {
            $this->actingAs($this->reviewer($role))
                ->get(route('staff-leave.index'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('staff/leave-review')
                    ->has('requests.data', 1)
                    ->where('requests.data.0.status', 'pending')
                    ->where('filters.status', 'pending'));
        }
    }

    public function test_review_queue_filters_by_status_and_employee_name_search(): void
    {
        $budi = $this->pendingRequest();
        $budi->employee->update(['name' => 'Budi Santoso']);

        $siti = $this->pendingRequest();
        $siti->employee->update(['name' => 'Siti Aminah']);

        $agus = $this->pendingRequest(['status' => LeaveRequestStatus::Approved]);
        $agus->employee->update(['name' => 'Agus Wijaya']);

        $this->actingAs($this->reviewer('admin'))
            ->get(route('staff-leave.index', ['search' => 'Budi']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('requests.data', 1)
                ->where('requests.data.0.employee_name', 'Budi Santoso')
                ->where('filters.search', 'Budi'));

        $this->actingAs($this->reviewer('admin'))
            ->get(route('staff-leave.index', ['status' => 'approved']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('requests.data', 1)
                ->where('requests.data.0.employee_name', 'Agus Wijaya')
                ->where('filters.status', 'approved'));
    }

    public function test_review_queue_paginates_fifteen_rows_per_page(): void
    {
        EmployeeLeaveRequest::factory()->count(16)->create();

        $this->actingAs($this->reviewer('admin'))
            ->get(route('staff-leave.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('requests.data', 15)
                ->where('requests.total', 16)
                ->where('requests.current_page', 1));
    }

    public function test_admin_can_approve_a_pending_request_with_a_note(): void
    {
        $reviewer = $this->reviewer('admin');
        $leaveRequest = $this->pendingRequest();

        $this->actingAs($reviewer)
            ->from(route('staff-leave.index'))
            ->put(route('staff-leave.approve', ['leave_request' => $leaveRequest->id]), [
                'review_note' => 'Surat dokter lengkap. Disetujui.',
            ])
            ->assertRedirect(route('staff-leave.index'));

        $leaveRequest->refresh();

        $this->assertSame(LeaveRequestStatus::Approved, $leaveRequest->status);
        $this->assertSame('Surat dokter lengkap. Disetujui.', $leaveRequest->review_note);
        $this->assertSame($reviewer->id, $leaveRequest->reviewed_by_user_id);
        $this->assertNotNull($leaveRequest->reviewed_at);
    }

    public function test_principal_can_approve_ac16_10(): void
    {
        $leaveRequest = $this->pendingRequest();

        $this->actingAs($this->reviewer('principal'))
            ->put(route('staff-leave.approve', ['leave_request' => $leaveRequest->id]))
            ->assertRedirect();

        $this->assertSame(
            LeaveRequestStatus::Approved,
            $leaveRequest->fresh()->status,
        );
    }

    public function test_admin_can_reject_with_a_note_and_attendance_is_untouched(): void
    {
        $reviewer = $this->reviewer('admin');
        $leaveRequest = $this->pendingRequest([
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-07',
        ]);

        $this->actingAs($reviewer)
            ->put(route('staff-leave.reject', ['leave_request' => $leaveRequest->id]), [
                'review_note' => 'Kuota cuti tahunan sudah habis.',
            ])
            ->assertRedirect();

        $leaveRequest->refresh();

        $this->assertSame(LeaveRequestStatus::Rejected, $leaveRequest->status);
        $this->assertSame('Kuota cuti tahunan sudah habis.', $leaveRequest->review_note);
        $this->assertSame($reviewer->id, $leaveRequest->reviewed_by_user_id);
        $this->assertNotNull($leaveRequest->reviewed_at);

        $this->assertSame(0, EmployeeAttendance::count());
    }

    public function test_reject_requires_a_review_note(): void
    {
        $leaveRequest = $this->pendingRequest();

        $this->actingAs($this->reviewer('admin'))
            ->put(route('staff-leave.reject', ['leave_request' => $leaveRequest->id]), [])
            ->assertSessionHasErrors('review_note');

        $this->actingAs($this->reviewer('admin'))
            ->put(route('staff-leave.reject', ['leave_request' => $leaveRequest->id]), [
                'review_note' => '',
            ])
            ->assertSessionHasErrors('review_note');

        $this->assertSame(
            LeaveRequestStatus::Pending,
            $leaveRequest->fresh()->status,
        );
        $this->assertSame(0, EmployeeAttendance::count());
    }

    public function test_approve_writes_attendance_only_on_expected_days_ac16_07(): void
    {
        // Fri 2026-10-09 through Sun 2026-10-11: three calendar days, only
        // Friday is an expected working day.
        $leaveRequest = $this->pendingRequest([
            'type' => LeaveType::AnnualLeave,
            'start_date' => '2026-10-09',
            'end_date' => '2026-10-11',
        ]);

        $this->actingAs($this->reviewer('admin'))
            ->put(route('staff-leave.approve', ['leave_request' => $leaveRequest->id]))
            ->assertRedirect();

        $this->assertSame(1, EmployeeAttendance::count());

        $this->assertDatabaseHas('employee_attendances', [
            'employee_id' => $leaveRequest->employee_id,
            'date' => '2026-10-09',
            'status' => EmployeeAttendanceStatus::AnnualLeave->value,
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'scan_method' => null,
            'notes' => null,
        ]);

        $this->assertDatabaseMissing('employee_attendances', ['date' => '2026-10-10']);
        $this->assertDatabaseMissing('employee_attendances', ['date' => '2026-10-11']);
    }

    public function test_approve_maps_each_leave_type_to_the_matching_attendance_status(): void
    {
        $mapping = [
            LeaveType::Sick->value => EmployeeAttendanceStatus::Sick,
            LeaveType::Leave->value => EmployeeAttendanceStatus::Leave,
            LeaveType::AnnualLeave->value => EmployeeAttendanceStatus::AnnualLeave,
            LeaveType::OfficialDuty->value => EmployeeAttendanceStatus::OfficialDuty,
        ];

        foreach ($mapping as $type => $status) {
            $leaveRequest = $this->pendingRequest([
                'type' => $type,
                'start_date' => '2026-10-05',
                'end_date' => '2026-10-07',
            ]);

            $this->actingAs($this->reviewer('admin'))
                ->put(route('staff-leave.approve', ['leave_request' => $leaveRequest->id]))
                ->assertRedirect();

            $this->assertDatabaseHas('employee_attendances', [
                'employee_id' => $leaveRequest->employee_id,
                'date' => '2026-10-05',
                'status' => $status->value,
            ]);
            $this->assertDatabaseHas('employee_attendances', [
                'employee_id' => $leaveRequest->employee_id,
                'date' => '2026-10-06',
                'status' => $status->value,
            ]);
            $this->assertDatabaseHas('employee_attendances', [
                'employee_id' => $leaveRequest->employee_id,
                'date' => '2026-10-07',
                'status' => $status->value,
            ]);
        }

        $this->assertSame(12, EmployeeAttendance::count());
    }

    public function test_approve_does_not_overwrite_existing_present_or_late_records(): void
    {
        $leaveRequest = $this->pendingRequest([
            'type' => LeaveType::AnnualLeave,
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-07',
        ]);

        EmployeeAttendance::factory()->create([
            'employee_id' => $leaveRequest->employee_id,
            'date' => '2026-10-05',
            'status' => EmployeeAttendanceStatus::Present,
            'scan_method' => ScanMethod::Rfid,
        ]);
        EmployeeAttendance::factory()->create([
            'employee_id' => $leaveRequest->employee_id,
            'date' => '2026-10-06',
            'status' => EmployeeAttendanceStatus::Late,
            'late_minutes' => 20,
        ]);

        $this->actingAs($this->reviewer('admin'))
            ->put(route('staff-leave.approve', ['leave_request' => $leaveRequest->id]))
            ->assertRedirect();

        // Present and late stay untouched…
        $this->assertDatabaseHas('employee_attendances', [
            'employee_id' => $leaveRequest->employee_id,
            'date' => '2026-10-05',
            'status' => EmployeeAttendanceStatus::Present->value,
            'scan_method' => ScanMethod::Rfid->value,
        ]);
        $this->assertDatabaseHas('employee_attendances', [
            'employee_id' => $leaveRequest->employee_id,
            'date' => '2026-10-06',
            'status' => EmployeeAttendanceStatus::Late->value,
            'late_minutes' => 20,
        ]);

        // …the remaining expected day is written.
        $this->assertDatabaseHas('employee_attendances', [
            'employee_id' => $leaveRequest->employee_id,
            'date' => '2026-10-07',
            'status' => EmployeeAttendanceStatus::AnnualLeave->value,
        ]);
    }

    public function test_approve_replaces_other_existing_records_on_expected_days(): void
    {
        $leaveRequest = $this->pendingRequest([
            'type' => LeaveType::Sick,
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-05',
        ]);

        EmployeeAttendance::factory()->create([
            'employee_id' => $leaveRequest->employee_id,
            'date' => '2026-10-05',
            'status' => EmployeeAttendanceStatus::Absent,
            'notes' => 'Swept by the nightly cron.',
        ]);

        $this->actingAs($this->reviewer('admin'))
            ->put(route('staff-leave.approve', ['leave_request' => $leaveRequest->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('employee_attendances', [
            'employee_id' => $leaveRequest->employee_id,
            'date' => '2026-10-05',
            'status' => EmployeeAttendanceStatus::Sick->value,
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'scan_method' => null,
            'notes' => null,
        ]);
    }

    public function test_approving_an_already_resolved_request_is_a_noop(): void
    {
        $firstReviewer = $this->reviewer('admin');

        $leaveRequest = $this->pendingRequest([
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-07',
        ]);
        $leaveRequest->update([
            'status' => LeaveRequestStatus::Approved,
            'reviewed_by_user_id' => $firstReviewer->id,
            'reviewed_at' => Date::parse('2026-10-04 09:00:00'),
        ]);

        $this->actingAs($this->reviewer('admin'))
            ->put(route('staff-leave.approve', ['leave_request' => $leaveRequest->id]), [
                'review_note' => 'Second attempt.',
            ])
            ->assertRedirect()
            ->assertSessionHas('inertia.flash_data.toast.type', 'info');

        $leaveRequest->refresh();

        $this->assertSame(LeaveRequestStatus::Approved, $leaveRequest->status);
        $this->assertSame($firstReviewer->id, $leaveRequest->reviewed_by_user_id);
        $this->assertNull($leaveRequest->review_note);
        $this->assertSame(0, EmployeeAttendance::count());
    }

    public function test_tap_on_an_excused_day_returns_ignored_excused_ac16_07(): void
    {
        $leaveRequest = $this->pendingRequest([
            'type' => LeaveType::Sick,
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-05',
        ]);

        $this->actingAs($this->reviewer('admin'))
            ->put(route('staff-leave.approve', ['leave_request' => $leaveRequest->id]))
            ->assertRedirect();

        Date::setTestNow(Date::parse('2026-10-05 07:00:00', 'Asia/Jakarta'));

        $result = app(EmployeeScanService::class)->scanResolved(
            ScanMethod::Rfid,
            $leaveRequest->employee,
            'EMP-01',
            null,
        );

        $this->assertSame(ScanOutcome::IgnoredExcused, $result->outcome);

        $record = EmployeeAttendance::query()
            ->where('employee_id', $leaveRequest->employee_id)
            ->where('date', '2026-10-05')
            ->firstOrFail();

        $this->assertSame(EmployeeAttendanceStatus::Sick, $record->status);
        $this->assertNull($record->checked_in_at);
    }

    public function test_staff_role_user_cannot_review_ac16_10(): void
    {
        $leaveRequest = $this->pendingRequest();

        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->get(route('staff-leave.index'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->put(route('staff-leave.approve', ['leave_request' => $leaveRequest->id]))
            ->assertForbidden();

        $this->actingAs($staff)
            ->put(route('staff-leave.reject', ['leave_request' => $leaveRequest->id]), [
                'review_note' => 'Nope.',
            ])
            ->assertForbidden();

        $this->assertSame(
            LeaveRequestStatus::Pending,
            $leaveRequest->fresh()->status,
        );
        $this->assertSame(0, EmployeeAttendance::count());
    }

    public function test_unrelated_roles_cannot_review(): void
    {
        $leaveRequest = $this->pendingRequest();

        foreach (['teacher', 'student', 'parent', 'finance'] as $role) {
            $user = User::factory()->{$role}()->create();

            $this->actingAs($user)
                ->get(route('staff-leave.index'))
                ->assertForbidden();

            $this->actingAs($user)
                ->put(route('staff-leave.approve', ['leave_request' => $leaveRequest->id]))
                ->assertForbidden();

            $this->actingAs($user)
                ->put(route('staff-leave.reject', ['leave_request' => $leaveRequest->id]), [
                    'review_note' => 'Nope.',
                ])
                ->assertForbidden();
        }

        $this->assertSame(
            LeaveRequestStatus::Pending,
            $leaveRequest->fresh()->status,
        );
        $this->assertSame(0, EmployeeAttendance::count());
    }

    public function test_reviewers_can_download_the_leave_attachment(): void
    {
        Storage::fake('local');

        $leaveRequest = EmployeeLeaveRequest::factory()
            ->withAttachment()
            ->create();
        Storage::disk('local')->put((string) $leaveRequest->attachment_path, 'pdf-bytes');

        $this->actingAs($this->reviewer('admin'))
            ->get(route('staff-leave.attachment', ['leave_request' => $leaveRequest->id]))
            ->assertOk();

        $this->actingAs(User::factory()->create())
            ->get(route('staff-leave.attachment', ['leave_request' => $leaveRequest->id]))
            ->assertForbidden();
    }
}

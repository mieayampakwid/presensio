<?php

namespace Tests\Feature\Staff;

use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveType;
use App\Models\Employee;
use App\Models\EmployeeLeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MyLeaveTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_non_employee_users_receive_403_on_index_store_and_destroy(): void
    {
        $student = User::factory()->student()->create();
        $staffWithoutEmployee = User::factory()->staff()->create();
        $parent = User::factory()->parent()->create();

        $existing = EmployeeLeaveRequest::factory()->create();

        foreach ([$student, $staffWithoutEmployee, $parent] as $user) {
            $this->actingAs($user)
                ->get(route('my-leave.index'))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('my-leave.store'), $this->payload())
                ->assertForbidden();

            $this->actingAs($user)
                ->delete(route('my-leave.destroy', ['leave_request' => $existing->id]))
                ->assertForbidden();
        }

        $this->assertSame(1, EmployeeLeaveRequest::count());
        $this->assertSame(
            LeaveRequestStatus::Pending,
            $existing->fresh()->status,
        );
    }

    public function test_employee_can_submit_a_leave_request(): void
    {
        [$user, $employee] = $this->employeeUser();

        $this->actingAs($user)
            ->post(route('my-leave.store'), $this->payload([
                'type' => 'annual_leave',
                'start_date' => '2026-10-05',
                'end_date' => '2026-10-07',
            ]))
            ->assertRedirect(route('my-leave.index'));

        $this->assertDatabaseHas('employee_leave_requests', [
            'employee_id' => $employee->id,
            'type' => LeaveType::AnnualLeave->value,
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-07',
            'status' => LeaveRequestStatus::Pending->value,
            'attachment_path' => null,
        ]);
    }

    public function test_employee_can_submit_a_request_with_an_attachment(): void
    {
        Storage::fake('local');
        [$user, $employee] = $this->employeeUser();

        $attachment = UploadedFile::fake()->create('surat-tugas.pdf', 100, 'application/pdf');

        $this->actingAs($user)
            ->post(route('my-leave.store'), $this->payload([
                'type' => 'official_duty',
                'attachment' => $attachment,
            ]))
            ->assertRedirect(route('my-leave.index'));

        $request = EmployeeLeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        $this->assertNotNull($request->attachment_path);
        $this->assertStringStartsWith('leave-attachments/', (string) $request->attachment_path);
        Storage::disk('local')->assertExists((string) $request->attachment_path);
    }

    public function test_reversed_dates_and_unknown_type_are_rejected(): void
    {
        [$user] = $this->employeeUser();

        $this->actingAs($user)
            ->post(route('my-leave.store'), $this->payload([
                'start_date' => '2026-10-07',
                'end_date' => '2026-10-05',
            ]))
            ->assertSessionHasErrors('end_date');

        $this->actingAs($user)
            ->post(route('my-leave.store'), $this->payload(['type' => 'vacation']))
            ->assertSessionHasErrors('type');

        $this->actingAs($user)
            ->post(route('my-leave.store'), $this->payload(['reason' => '']))
            ->assertSessionHasErrors('reason');

        $this->assertSame(0, EmployeeLeaveRequest::count());
    }

    public function test_disallowed_attachment_types_and_oversized_files_are_rejected(): void
    {
        Storage::fake('local');
        [$user] = $this->employeeUser();

        $this->actingAs($user)
            ->post(route('my-leave.store'), $this->payload([
                'attachment' => UploadedFile::fake()->create('note.txt', 10),
            ]))
            ->assertSessionHasErrors('attachment');

        // Config default is 5120 KB — one KB over is rejected.
        $this->actingAs($user)
            ->post(route('my-leave.store'), $this->payload([
                'attachment' => UploadedFile::fake()->create('surat.pdf', 5121),
            ]))
            ->assertSessionHasErrors('attachment');

        $this->assertSame(0, EmployeeLeaveRequest::count());
    }

    public function test_overlapping_pending_or_approved_request_returns_422(): void
    {
        [$user, $employee] = $this->employeeUser();

        $existing = EmployeeLeaveRequest::factory()
            ->forEmployee($employee)
            ->create([
                'type' => LeaveType::AnnualLeave,
                'start_date' => '2026-10-05',
                'end_date' => '2026-10-07',
            ]);

        // Overlapping the pending request is blocked.
        $this->actingAs($user)
            ->post(route('my-leave.store'), $this->payload([
                'start_date' => '2026-10-07',
                'end_date' => '2026-10-08',
            ]))
            ->assertSessionHasErrors('start_date');

        // Still pending after approval, the same range stays blocked (AC-16-08).
        $existing->update(['status' => LeaveRequestStatus::Approved]);

        $this->actingAs($user)
            ->post(route('my-leave.store'), $this->payload([
                'start_date' => '2026-10-05',
                'end_date' => '2026-10-05',
            ]))
            ->assertSessionHasErrors('start_date');

        $this->assertSame(1, EmployeeLeaveRequest::count());
    }

    public function test_non_overlapping_and_adjacent_requests_succeed(): void
    {
        [$user, $employee] = $this->employeeUser();

        EmployeeLeaveRequest::factory()
            ->forEmployee($employee)
            ->create([
                'start_date' => '2026-10-05',
                'end_date' => '2026-10-07',
            ]);

        // Adjacent — the new request starts where the pending one ends.
        $this->actingAs($user)
            ->post(route('my-leave.store'), $this->payload([
                'start_date' => '2026-10-08',
                'end_date' => '2026-10-08',
            ]))
            ->assertRedirect(route('my-leave.index'));

        // Fully disjoint range.
        $this->actingAs($user)
            ->post(route('my-leave.store'), $this->payload([
                'type' => 'leave',
                'start_date' => '2026-10-12',
                'end_date' => '2026-10-13',
            ]))
            ->assertRedirect(route('my-leave.index'));

        $this->assertSame(3, EmployeeLeaveRequest::where('employee_id', $employee->id)->count());
    }

    public function test_another_employees_request_does_not_block_submission(): void
    {
        [$user, $employee] = $this->employeeUser();
        $other = Employee::factory()->create();

        EmployeeLeaveRequest::factory()
            ->forEmployee($other)
            ->create([
                'start_date' => '2026-10-05',
                'end_date' => '2026-10-07',
            ]);

        $this->actingAs($user)
            ->post(route('my-leave.store'), $this->payload([
                'start_date' => '2026-10-06',
                'end_date' => '2026-10-06',
            ]))
            ->assertRedirect(route('my-leave.index'));

        $this->assertSame(1, EmployeeLeaveRequest::where('employee_id', $employee->id)->count());
        $this->assertSame(2, EmployeeLeaveRequest::count());
    }

    public function test_index_lists_only_the_employees_own_requests(): void
    {
        [$user, $employee] = $this->employeeUser();
        $other = Employee::factory()->create();

        $mine = EmployeeLeaveRequest::factory()->forEmployee($employee)->create();
        EmployeeLeaveRequest::factory()->forEmployee($other)->create();

        $this->actingAs($user)
            ->get(route('my-leave.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('staff/my-leave')
                ->has('requests.data', 1)
                ->where('requests.data.0.id', $mine->id));
    }

    public function test_employee_can_cancel_a_pending_request(): void
    {
        [$user, $employee] = $this->employeeUser();

        $request = EmployeeLeaveRequest::factory()->forEmployee($employee)->create();

        $this->actingAs($user)
            ->delete(route('my-leave.destroy', ['leave_request' => $request->id]))
            ->assertRedirect(route('my-leave.index'));

        $this->assertSame(
            LeaveRequestStatus::Cancelled,
            $request->fresh()->status,
        );
    }

    public function test_cancelling_an_approved_request_returns_422(): void
    {
        [$user, $employee] = $this->employeeUser();

        $request = EmployeeLeaveRequest::factory()
            ->forEmployee($employee)
            ->approved()
            ->create();

        $this->actingAs($user)
            ->delete(route('my-leave.destroy', ['leave_request' => $request->id]))
            ->assertStatus(422);

        $this->assertSame(
            LeaveRequestStatus::Approved,
            $request->fresh()->status,
        );
    }

    public function test_employee_cannot_cancel_another_employees_request(): void
    {
        [$user] = $this->employeeUser();
        $other = Employee::factory()->create();

        $request = EmployeeLeaveRequest::factory()->forEmployee($other)->create();

        $this->actingAs($user)
            ->delete(route('my-leave.destroy', ['leave_request' => $request->id]))
            ->assertForbidden();

        $this->assertSame(
            LeaveRequestStatus::Pending,
            $request->fresh()->status,
        );
    }

    public function test_employee_can_download_their_own_attachment(): void
    {
        Storage::fake('local');
        [$user, $employee] = $this->employeeUser();

        $request = EmployeeLeaveRequest::factory()
            ->forEmployee($employee)
            ->withAttachment()
            ->create();
        Storage::disk('local')->put((string) $request->attachment_path, 'pdf-bytes');

        $this->actingAs($user)
            ->get(route('my-leave.attachment', ['leave_request' => $request->id]))
            ->assertOk();

        $this->actingAs(User::factory()->create())
            ->get(route('my-leave.attachment', ['leave_request' => $request->id]))
            ->assertForbidden();
    }
}

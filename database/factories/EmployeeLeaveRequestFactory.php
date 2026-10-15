<?php

namespace Database\Factories;

use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveType;
use App\Models\Employee;
use App\Models\EmployeeLeaveRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeLeaveRequest>
 */
class EmployeeLeaveRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'type' => LeaveType::Sick,
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
            'reason' => fake()->sentence(),
            'attachment_path' => null,
            'status' => LeaveRequestStatus::Pending,
            'review_note' => null,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
        ];
    }

    /**
     * Attach the request to a specific employee.
     */
    public function forEmployee(?Employee $employee = null): static
    {
        return $this->for($employee ?? Employee::factory());
    }

    public function leave(): static
    {
        return $this->state(fn () => ['type' => LeaveType::Leave]);
    }

    public function annualLeave(): static
    {
        return $this->state(fn () => ['type' => LeaveType::AnnualLeave]);
    }

    public function officialDuty(): static
    {
        return $this->state(fn () => ['type' => LeaveType::OfficialDuty]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => LeaveRequestStatus::Approved,
            'reviewed_by_user_id' => User::factory()->admin(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => LeaveRequestStatus::Rejected,
            'reviewed_by_user_id' => User::factory()->admin(),
            'reviewed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => LeaveRequestStatus::Cancelled]);
    }

    /**
     * Opaque local-disk path shape the controller stores (the file itself
     * only exists in tests that pair this with Storage::fake + a real put).
     */
    public function withAttachment(): static
    {
        return $this->state(fn () => [
            'attachment_path' => 'leave-attachments/'.bin2hex(random_bytes(20)).'.pdf',
        ]);
    }
}

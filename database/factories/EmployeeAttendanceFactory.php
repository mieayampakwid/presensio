<?php

namespace Database\Factories;

use App\Enums\EmployeeAttendanceStatus;
use App\Enums\ScanMethod;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeAttendance>
 */
class EmployeeAttendanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'date' => fake()->date(),
            'status' => EmployeeAttendanceStatus::Present,
            'checked_in_at' => now(),
            'checked_out_at' => null,
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'scan_method' => ScanMethod::Rfid,
            'override_by_user_id' => null,
            'overridden_at' => null,
            'notes' => null,
        ];
    }

    public function late(int $minutes = 15): static
    {
        return $this->state(fn () => [
            'status' => EmployeeAttendanceStatus::Late,
            'late_minutes' => $minutes,
        ]);
    }

    public function absent(): static
    {
        return $this->state(fn () => [
            'status' => EmployeeAttendanceStatus::Absent,
            'checked_in_at' => null,
            'checked_out_at' => null,
            'scan_method' => null,
        ]);
    }
}

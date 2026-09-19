<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Enums\ScanMethod;
use App\Models\Attendance;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'date' => today(),
            'status' => AttendanceStatus::Present,
            'checked_in_at' => null,
            'checked_out_at' => null,
            'scan_method' => ScanMethod::Rfid,
            'override_by_user_id' => null,
            'notes' => null,
        ];
    }

    public function late(): static
    {
        return $this->state(fn () => ['status' => AttendanceStatus::Late]);
    }

    public function absent(): static
    {
        return $this->state(fn () => [
            'status' => AttendanceStatus::Absent,
            // System-generated sweep record (spec 03 §Requirements 3).
            'scan_method' => null,
        ]);
    }

    public function sick(): static
    {
        return $this->state(fn () => [
            'status' => AttendanceStatus::Sick,
            'scan_method' => null,
        ]);
    }

    public function leave(): static
    {
        return $this->state(fn () => [
            'status' => AttendanceStatus::Leave,
            'scan_method' => null,
        ]);
    }

    public function checkedOut(): static
    {
        return $this->state(fn () => [
            'status' => AttendanceStatus::Present,
            'checked_in_at' => today()->setTime(7, 0),
            'checked_out_at' => today()->setTime(13, 0),
        ]);
    }

    /**
     * Excuse-injection style record: no scan method at all.
     */
    public function withoutMethod(): static
    {
        return $this->state(fn () => ['scan_method' => null]);
    }
}

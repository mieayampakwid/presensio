<?php

namespace Database\Factories;

use App\Enums\ScanMethod;
use App\Enums\ScanOutcome;
use App\Models\ScanEvent;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScanEvent>
 */
class ScanEventFactory extends Factory
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
            'scan_method' => ScanMethod::Rfid,
            'identifier' => fake()->unique()->numerify('############'),
            'scanned_at' => now(),
            'outcome' => ScanOutcome::CheckIn,
        ];
    }

    /**
     * QR attempts never store the token — identifier stays null.
     */
    public function qr(): static
    {
        return $this->state(fn () => [
            'scan_method' => ScanMethod::DynamicQr,
            'identifier' => null,
        ]);
    }

    /**
     * Unknown credential: the attempt resolved to nobody.
     */
    public function unknown(): static
    {
        return $this->state(fn () => [
            'student_id' => null,
            'outcome' => ScanOutcome::ErrorUnknownCredential,
        ]);
    }
}

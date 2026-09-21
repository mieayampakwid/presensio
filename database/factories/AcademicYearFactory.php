<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->unique()->numerify('20##');

        return [
            'name' => $start.'/'.((int) $start + 1),
            'starts_at' => $start.'-07-01',
            'ends_at' => ((int) $start + 1).'-06-30',
            // Inactive by default — only the bootstrap migration year is
            // active, so tests creating extra years never break the
            // exactly-one-active invariant.
            'is_active' => false,
        ];
    }

    /**
     * The active year (deactivates no one — callers manage exclusivity).
     */
    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }
}

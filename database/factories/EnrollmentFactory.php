<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'class_id' => SchoolClass::factory(),
            // A long-past default so date-effective lookups resolve for
            // any record date a test is likely to use; pass started_on
            // explicitly when precision matters.
            'started_on' => '2000-01-01',
            'ended_on' => null,
        ];
    }
}

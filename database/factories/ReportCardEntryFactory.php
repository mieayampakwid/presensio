<?php

namespace Database\Factories;

use App\Models\ReportCardEntry;
use App\Models\Semester;
use App\Models\Student;

/**
 * @extends Factory<ReportCardEntry>
 */
class ReportCardEntryFactory extends Factory
{
    protected $model = ReportCardEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'semester_id' => Semester::factory(),
            'student_id' => Student::factory(),
            'homeroom_notes' => null,
            'extracurricular_notes' => null,
            'promotion_decision' => null,
        ];
    }
}

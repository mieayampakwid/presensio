<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\FeeType;
use App\Models\Student;
use App\Models\StudentFeeDiscount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentFeeDiscount>
 */
class StudentFeeDiscountFactory extends Factory
{
    protected $model = StudentFeeDiscount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'fee_type_id' => FeeType::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'percent' => '50.00',
            'fixed_amount' => null,
            'reason' => 'Beasiswa prestasi',
        ];
    }

    public function fixed(string $amount = '100000.00'): static
    {
        return $this->state(fn () => [
            'percent' => null,
            'fixed_amount' => $amount,
        ]);
    }
}

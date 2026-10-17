<?php

namespace Database\Factories;

use App\Enums\BillStatus;
use App\Models\AcademicYear;
use App\Models\Bill;
use App\Models\FeeType;
use App\Models\Student;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bill>
 */
class BillFactory extends Factory
{
    protected $model = Bill::class;

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
            'period_key' => fake()->date('Y-m'),
            'title' => 'SPP '.fake()->monthName().' '.fake()->year(),
            'amount' => '350000.00',
            'discount_amount' => '0.00',
            'discount_reason' => null,
            'paid_amount' => '0.00',
            'status' => BillStatus::Unpaid,
            'recognized_at' => null,
            'is_opening' => false,
            'written_off_at' => null,
            'write_off_reason' => null,
            'due_date' => fake()->date(),
            'cancelled_at' => null,
            'cancel_reason' => null,
        ];
    }

    public function recognized(?string $date = null): static
    {
        return $this->state(fn (array $attributes) => [
            'recognized_at' => $date ?? $attributes['due_date'],
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'paid_amount' => Money::sub($attributes['amount'], $attributes['discount_amount']),
            'status' => BillStatus::Paid,
        ]);
    }

    public function partiallyPaid(string $paid = '100000.00'): static
    {
        return $this->state(fn () => [
            'paid_amount' => $paid,
            'status' => BillStatus::PartiallyPaid,
        ]);
    }

    public function cancelled(string $reason = 'Siswa keluar/lulus'): static
    {
        return $this->state(fn () => [
            'status' => BillStatus::Cancelled,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);
    }
}

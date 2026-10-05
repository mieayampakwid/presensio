<?php

namespace Database\Factories;

use App\Enums\EmploymentType;
use App\Models\Employee;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => fake()->name(),
            'employee_number' => fake()->unique()->numerify('##################'),
            'phone_number' => fake()->e164PhoneNumber(),
            'employment_type' => EmploymentType::Permanent,
            'position' => 'Guru',
            'working_days' => null,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the employee is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the employee has a linked user account.
     */
    public function forUser(?User $user = null): static
    {
        return $this->state(fn () => [
            'user_id' => $user !== null ? $user->id : User::factory()->create()->id,
        ]);
    }

    /**
     * Indicate that the employee is a teacher.
     */
    public function teacher(): static
    {
        return $this->afterCreating(function (Employee $employee) {
            Teacher::create(['employee_id' => $employee->id]);
        });
    }
}

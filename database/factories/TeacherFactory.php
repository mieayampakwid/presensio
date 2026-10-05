<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Teacher>
 */
class TeacherFactory extends Factory
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
        ];
    }

    /**
     * Indicate that the teacher is linked to a user.
     */
    public function forUser(?User $user = null): static
    {
        return $this->for(
            Employee::factory()->forUser($user),
            'employee'
        );
    }

    /**
     * Pass-through convenience when creating: if caller passes employee-level attributes
     * like name, user_id, phone_number, etc., delegate them to the created employee.
     */
    public function create($attributes = [], ?Model $parent = null)
    {
        $employeeAttributes = [];
        foreach (['name', 'user_id', 'employee_number', 'teacher_number', 'phone_number', 'is_active', 'employment_type'] as $key) {
            if (array_key_exists($key, $attributes)) {
                $targetKey = $key === 'teacher_number' ? 'employee_number' : $key;
                $employeeAttributes[$targetKey] = $attributes[$key];
                unset($attributes[$key]);
            }
        }

        if (! empty($employeeAttributes) && ! isset($attributes['employee_id'])) {
            $attributes['employee_id'] = Employee::factory()->create($employeeAttributes)->id;
        }

        return parent::create($attributes, $parent);
    }
}

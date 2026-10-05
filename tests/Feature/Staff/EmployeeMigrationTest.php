<?php

namespace Tests\Feature\Staff;

use App\Models\Employee;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ac_16_01_migration_moves_teacher_profiles_to_employees_and_resolves_classes(): void
    {
        $user = User::factory()->teacher()->create();

        $employee1 = Employee::factory()->create([
            'user_id' => $user->id,
            'name' => 'Budi Santoso, S.Pd.',
            'employee_number' => '198501012010011001',
            'phone_number' => '+628111222333',
            'employment_type' => 'permanent',
            'is_active' => true,
        ]);

        $teacher1 = Teacher::factory()->create([
            'employee_id' => $employee1->id,
        ]);

        $employee2 = Employee::factory()->create([
            'user_id' => null,
            'name' => 'Siti Aminah, M.Pd.',
            'employee_number' => '198702022011012002',
            'phone_number' => '+628111444555',
            'employment_type' => 'permanent',
            'is_active' => true,
        ]);

        $teacher2 = Teacher::factory()->create([
            'employee_id' => $employee2->id,
        ]);

        $class = SchoolClass::factory()->create(['teacher_id' => $teacher1->id]);

        // Verify class teacher relationship resolves
        $this->assertSame($teacher1->id, $class->fresh()->teacher_id);
        $this->assertSame($employee1->id, $class->fresh()->teacher->employee->id);
        $this->assertSame('Budi Santoso, S.Pd.', $class->fresh()->teacher->employee->name);
        $this->assertSame('198501012010011001', $class->fresh()->teacher->employee->employee_number);
        $this->assertSame('+628111222333', $class->fresh()->teacher->employee->phone_number);
        $this->assertSame($user->id, $class->fresh()->teacher->employee->user_id);

        // Verify teacher 2
        $this->assertSame('Siti Aminah, M.Pd.', $teacher2->fresh()->employee->name);
        $this->assertNull($teacher2->fresh()->employee->user_id);
    }
}

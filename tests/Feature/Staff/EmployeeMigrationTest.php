<?php

namespace Tests\Feature\Staff;

use App\Models\Employee;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    public function test_migrate_data_and_rollback_data_static_methods(): void
    {
        $migration = require database_path('migrations/2026_10_05_090349_create_employees_table.php');

        Schema::table('teachers', function ($table) {
            $table->string('name')->nullable();
            $table->string('teacher_number')->nullable();
            $table->string('phone_number')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
        });

        $user = User::factory()->teacher()->create();

        $placeholderEmployee = Employee::factory()->create();

        $teacherId = DB::table('teachers')->insertGetId([
            'employee_id' => $placeholderEmployee->id,
            'user_id' => $user->id,
            'name' => 'Budi Santoso',
            'teacher_number' => '123456',
            'phone_number' => '0812345678',
        ]);

        $class = SchoolClass::factory()->create(['teacher_id' => $teacherId]);

        $migration::migrateData();

        $teacher = DB::table('teachers')->where('id', $teacherId)->first();
        $this->assertNotEquals($placeholderEmployee->id, $teacher->employee_id);

        $employee = DB::table('employees')->where('id', $teacher->employee_id)->first();
        $this->assertNotNull($employee);
        $this->assertSame('Budi Santoso', $employee->name);
        $this->assertSame('123456', $employee->employee_number);
        $this->assertSame('0812345678', $employee->phone_number);
        $this->assertSame($user->id, $employee->user_id);
        $this->assertSame($teacherId, $class->fresh()->teacher_id);

        $migration::rollbackData();

        $restoredTeacher = DB::table('teachers')->where('id', $teacherId)->first();
        $this->assertSame('Budi Santoso', $restoredTeacher->name);
        $this->assertSame('123456', $restoredTeacher->teacher_number);
        $this->assertSame('0812345678', $restoredTeacher->phone_number);
        $this->assertSame($user->id, $restoredTeacher->user_id);

        Schema::table('teachers', function ($table) {
            $table->dropColumn(['name', 'teacher_number', 'phone_number', 'user_id']);
        });
    }
}

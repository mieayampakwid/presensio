<?php

namespace Tests\Feature\Seeders;

use App\Enums\EmployeeAttendanceStatus;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\ClassSubject;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_populates_all_models_via_dedicated_seeders(): void
    {
        $this->seed(DatabaseSeeder::class);

        // 1. Users & Roles
        $this->assertDatabaseHas('users', ['username' => 'admin']);
        $this->assertDatabaseHas('users', ['username' => 'principal']);
        $this->assertDatabaseHas('users', ['username' => 'teacher1']);
        $this->assertDatabaseHas('users', ['username' => 'teacher2']);
        $this->assertDatabaseHas('users', ['username' => 'staff1']);
        $this->assertDatabaseHas('users', ['username' => 'student1']);
        $this->assertDatabaseHas('users', ['username' => 'student2']);
        $this->assertDatabaseHas('users', ['username' => 'parent1']);
        $this->assertDatabaseHas('users', ['username' => 'parent2']);

        $staffUser = User::where('username', 'staff1')->first();
        $this->assertNotNull($staffUser);
        $this->assertTrue($staffUser->hasRole(UserRole::Staff));

        $principalUser = User::where('username', 'principal')->first();
        $this->assertNotNull($principalUser);
        $this->assertTrue($principalUser->hasRole(UserRole::Principal));

        // 2. Employees & Teachers
        $this->assertGreaterThanOrEqual(5, Employee::count());
        $this->assertSame(2, Teacher::count());

        $staffEmployee = Employee::where('position', 'Tata Usaha')->first();
        $this->assertNotNull($staffEmployee);
        $this->assertSame($staffUser->id, $staffEmployee->user_id);

        $securityEmployee = Employee::where('position', 'Petugas Keamanan')->first();
        $this->assertNotNull($securityEmployee);
        $this->assertSame([1, 2, 3, 4, 5], $securityEmployee->working_days);

        // 3. Subjects & Classes
        $this->assertGreaterThanOrEqual(9, Subject::count());
        $this->assertDatabaseHas('classes', ['name' => 'Kelas 5A']);
        $this->assertDatabaseHas('classes', ['name' => 'Kelas 5B']);
        $this->assertTrue(ClassSubject::exists());

        // 4. Students & Guardians
        $this->assertSame(8, Student::count());
        $this->assertDatabaseHas('students', ['full_name' => 'Ahmad Fauzi']);
        $this->assertSame(4, Guardian::count());

        $slamet = Guardian::where('name', 'Slamet Riyadi')->first();
        $this->assertNotNull($slamet);
        $this->assertSame(2, $slamet->students()->count());

        // 5. RFID Cards
        $this->assertDatabaseHas('rfid_cards', ['rfid_number' => 'CARD-001']);
        $this->assertDatabaseHas('rfid_cards', ['rfid_number' => 'CARD-EMP-001']);
        $this->assertDatabaseHas('rfid_cards', ['rfid_number' => 'CARD-EMP-002']);

        // 6. Student & Employee Attendances
        $this->assertGreaterThan(0, Attendance::count());
        $this->assertGreaterThan(0, EmployeeAttendance::count());
        $this->assertTrue(EmployeeAttendance::where('status', EmployeeAttendanceStatus::Present)->exists());

        // 7. Pages accessibility smoke test
        $admin = User::where('username', 'admin')->first();
        $response = $this->actingAs($admin)->get(route('staff-attendance.index'));
        $response->assertOk();

        $responseEmployees = $this->actingAs($admin)->get(route('employees.index'));
        $responseEmployees->assertOk();
    }
}

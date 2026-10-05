<?php

namespace Tests\Feature\Employees;

use App\Enums\EmploymentType;
use App\Enums\UserRole;
use App\Models\ClassSubject;
use App\Models\Employee;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_employees_list(): void
    {
        $admin = User::factory()->admin()->create();
        Employee::factory()->create(['name' => 'Pak Joko Widodo']);

        $this->actingAs($admin)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertSee('Pak Joko Widodo');
    }

    public function test_admin_can_search_employees(): void
    {
        $admin = User::factory()->admin()->create();
        Employee::factory()->create(['name' => 'Pak Joko Widodo']);
        Employee::factory()->create(['name' => 'Ibu Megawati']);

        $this->actingAs($admin)
            ->get(route('employees.index', ['search' => 'Megawati']))
            ->assertOk()
            ->assertSee('Ibu Megawati')
            ->assertDontSee('Pak Joko Widodo');
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_roles_are_forbidden(string $role): void
    {
        $user = User::factory()->{$role}()->create();

        $this->actingAs($user)
            ->get(route('employees.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_an_employee_with_working_days_and_employment_type(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('employees.store'), [
                'name' => 'Bambang Sudarmono',
                'employee_number' => '198001012005011003',
                'phone_number' => '+628123456789',
                'employment_type' => EmploymentType::Pns->value,
                'position' => 'Staf Tata Usaha',
                'working_days' => [1, 2, 3, 4, 5],
                'is_active' => true,
                'is_teacher' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('employees.index'));

        $employee = Employee::where('name', 'Bambang Sudarmono')->first();
        $this->assertNotNull($employee);
        $this->assertSame('198001012005011003', $employee->employee_number);
        $this->assertSame(EmploymentType::Pns, $employee->employment_type);
        $this->assertSame([1, 2, 3, 4, 5], $employee->working_days);
        $this->assertFalse($employee->isTeacher());
    }

    public function test_creating_employee_with_is_teacher_creates_teacher_record(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('employees.store'), [
                'name' => 'Guru Baru, S.Pd.',
                'employee_number' => '199001012020011001',
                'employment_type' => EmploymentType::Contract->value,
                'position' => 'Guru Matematika',
                'is_active' => true,
                'is_teacher' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('employees.index'));

        $employee = Employee::where('name', 'Guru Baru, S.Pd.')->first();
        $this->assertNotNull($employee);
        $this->assertTrue($employee->isTeacher());
        $this->assertNotNull($employee->teacher);
    }

    public function test_working_days_must_be_subset_of_operational_days(): void
    {
        $admin = User::factory()->admin()->create();
        SchoolSetting::first()->update(['school_operational_days' => [1, 2, 3, 4, 5]]);

        // Sunday (7) is not operational
        $this->actingAs($admin)
            ->post(route('employees.store'), [
                'name' => 'Staff Weekend',
                'employment_type' => EmploymentType::Honorary->value,
                'working_days' => [1, 7],
                'is_active' => true,
            ])
            ->assertSessionHasErrors('working_days');
    }

    public function test_toggling_is_teacher_off_is_blocked_with_422_when_referenced_by_class(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = Teacher::factory()->create();
        $employee = $teacher->employee;
        SchoolClass::factory()->create(['teacher_id' => $teacher->id]);

        $this->actingAs($admin)
            ->put(route('employees.update', $employee), [
                'name' => $employee->name,
                'employment_type' => $employee->employment_type->value,
                'is_active' => true,
                'is_teacher' => false,
            ])
            ->assertSessionHasErrors('is_teacher');

        $this->assertTrue($employee->fresh()->isTeacher());
    }

    public function test_toggling_is_teacher_off_is_blocked_with_422_when_referenced_by_subject(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = Teacher::factory()->create();
        $employee = $teacher->employee;
        $class = SchoolClass::factory()->create();
        $subject = Subject::factory()->create();
        ClassSubject::factory()->create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);

        $this->actingAs($admin)
            ->put(route('employees.update', $employee), [
                'name' => $employee->name,
                'employment_type' => $employee->employment_type->value,
                'is_active' => true,
                'is_teacher' => false,
            ])
            ->assertSessionHasErrors('is_teacher');

        $this->assertTrue($employee->fresh()->isTeacher());
    }

    public function test_toggling_is_teacher_off_deletes_teacher_record_when_not_referenced(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = Teacher::factory()->create();
        $employee = $teacher->employee;

        $this->actingAs($admin)
            ->put(route('employees.update', $employee), [
                'name' => $employee->name,
                'employment_type' => $employee->employment_type->value,
                'is_active' => true,
                'is_teacher' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($employee->fresh()->isTeacher());
        $this->assertDatabaseMissing('teachers', ['employee_id' => $employee->id]);
    }

    public function test_linking_user_with_student_role_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $studentUser = User::factory()->student()->create();

        $this->actingAs($admin)
            ->post(route('employees.store'), [
                'name' => 'Siswa Jadi Pegawai',
                'employment_type' => EmploymentType::Honorary->value,
                'user_id' => $studentUser->id,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('user_id');
    }

    public function test_linking_user_to_non_teacher_employee_grants_staff_role(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['username' => 'staff.tu']);

        $this->actingAs($admin)
            ->post(route('employees.store'), [
                'name' => 'Staf Tata Usaha',
                'employment_type' => EmploymentType::Permanent->value,
                'user_id' => $user->id,
                'is_active' => true,
                'is_teacher' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->fresh()->hasRole(UserRole::Staff));
    }

    public function test_linking_user_to_teacher_employee_grants_teacher_role(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['username' => 'guru.ipa']);

        $this->actingAs($admin)
            ->post(route('employees.store'), [
                'name' => 'Guru IPA',
                'employment_type' => EmploymentType::Permanent->value,
                'user_id' => $user->id,
                'is_active' => true,
                'is_teacher' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->fresh()->hasRole(UserRole::Teacher));
    }

    public function test_admin_can_delete_unlinked_employee_without_classes(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($admin)
            ->delete(route('employees.destroy', $employee))
            ->assertRedirect(route('employees.index'));

        $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
    }

    public static function nonAdminRoles(): array
    {
        return [
            'teacher' => ['teacher'],
            'student' => ['student'],
            'parent' => ['parent'],
        ];
    }
}

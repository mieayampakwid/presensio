<?php

namespace App\Services\Employees;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    /**
     * Create an employee and conditionally link teacher profile & user role.
     *
     * @param  array<string, mixed>  $validated
     */
    public function createEmployee(array $validated, bool $isTeacher): Employee
    {
        return DB::transaction(function () use ($validated, $isTeacher): Employee {
            unset($validated['is_teacher']);

            $employee = Employee::create($validated);

            if ($isTeacher) {
                Teacher::create(['employee_id' => $employee->id]);
            }

            if ($employee->user_id !== null) {
                $user = User::find($employee->user_id);
                if ($user !== null) {
                    $targetRole = $isTeacher ? UserRole::Teacher : UserRole::Staff;
                    if (! $user->hasRole($targetRole)) {
                        $user->roleGrants()->create(['role' => $targetRole, 'created_at' => now()]);
                    }
                }
            }

            $employee->recordAudit('created', null, $employee->toArray());

            return $employee;
        });
    }

    /**
     * Update an employee and sync teacher profile & user role.
     *
     * @param  array<string, mixed>  $validated
     */
    public function updateEmployee(Employee $employee, array $validated, bool $isTeacher): Employee
    {
        return DB::transaction(function () use ($employee, $validated, $isTeacher): Employee {
            $old = $employee->only([
                'name',
                'employee_number',
                'phone_number',
                'employment_type',
                'position',
                'working_days',
                'is_active',
                'user_id',
            ]);

            unset($validated['is_teacher']);

            // The form omits working_days when following all operational days.
            $validated['working_days'] = $validated['working_days'] ?? null;

            $employee->update($validated);

            $wasTeacher = $employee->isTeacher();
            if ($isTeacher && ! $wasTeacher) {
                Teacher::create(['employee_id' => $employee->id]);
            } elseif (! $isTeacher && $wasTeacher) {
                $employee->teacher?->delete();
            }

            if ($employee->user_id !== null) {
                $user = User::find($employee->user_id);
                if ($user !== null) {
                    $targetRole = $isTeacher ? UserRole::Teacher : UserRole::Staff;
                    if (! $user->hasRole($targetRole)) {
                        $user->roleGrants()->create(['role' => $targetRole, 'created_at' => now()]);
                    }
                }
            }

            $employee->recordAudit('updated', $old, $employee->toArray());

            return $employee;
        });
    }

    /**
     * Check reasons why the employee cannot be deleted.
     *
     * @return list<string>
     */
    public function deletionBlockers(Employee $employee): array
    {
        $blockers = [];

        if ($employee->isTeacher() && $employee->teacher !== null) {
            if ($employee->teacher->classes()->exists()) {
                $blockers[] = __('toasts.employees.delete_blocked_homeroom');
            }

            if ($employee->teacher->classSubjects()->exists()) {
                $blockers[] = __('toasts.employees.delete_blocked_assignments');
            }
        }

        if ($employee->user_id !== null) {
            $blockers[] = __('toasts.common.delete_blocked_user_link');
        }

        if ($employee->attendances()->exists()) {
            $blockers[] = __('toasts.employees.delete_blocked_attendance');
        }

        return $blockers;
    }

    /**
     * Delete the employee and associated teacher record.
     */
    public function deleteEmployee(Employee $employee): void
    {
        DB::transaction(function () use ($employee): void {
            $old = $employee->toArray();

            $employee->teacher?->delete();
            $employee->delete();

            $employee->recordAudit('deleted', $old, null);
        });
    }
}

<?php

namespace App\Services\Employees;

use App\Enums\EmploymentType;
use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use App\Services\SchoolSettings;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class EmployeeViewService
{
    public function __construct(
        private readonly SchoolSettings $schoolSettings,
    ) {}

    /**
     * Get paginated employees list formatted for the index page.
     *
     * @return LengthAwarePaginator<int, array{
     *     id: int,
     *     name: string,
     *     employee_number: string|null,
     *     phone_number: string|null,
     *     employment_type: string,
     *     employment_type_label: string,
     *     position: string|null,
     *     shift_name: string|null,
     *     working_days: list<int>|null,
     *     is_active: bool,
     *     is_teacher: bool,
     *     user: array{id: int, username: string}|null
     * }>
     */
    public function getPaginatedEmployees(string $search): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, Employee> $paginator */
        $paginator = Employee::query()
            ->with(['user:id,username', 'teacher:id,employee_id', 'shift:id,name'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereLike('name', "%{$search}%")
                        ->orWhereLike('employee_number', "%{$search}%")
                        ->orWhereLike('phone_number', "%{$search}%")
                        ->orWhereLike('position', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return $paginator->through(fn (Employee $employee): array => [
            'id' => $employee->id,
            'name' => $employee->name,
            'employee_number' => $employee->employee_number,
            'phone_number' => $employee->phone_number,
            'employment_type' => $employee->employment_type->value,
            'employment_type_label' => $employee->employment_type->label(),
            'position' => $employee->position,
            'shift_name' => $employee->shift?->name,
            'working_days' => $employee->working_days,
            'is_active' => $employee->is_active,
            'is_teacher' => $employee->teacher !== null,
            'user' => $employee->user ? [
                'id' => $employee->user->id,
                'username' => $employee->user->username,
            ] : null,
        ]);
    }

    /**
     * Get common form options (employment types, shifts, operational days, available users).
     *
     * @return array{
     *     employment_types: list<array{value: string, label: string}>,
     *     shifts: array<int, array{id: int, name: string}>,
     *     operational_days: array<int, int>,
     *     available_users: array<int, array{id: int, username: string}>
     * }
     */
    public function getFormOptions(?int $currentUserId = null): array
    {
        return [
            'employment_types' => $this->employmentTypeOptions(),
            'shifts' => $this->shiftOptions(),
            'operational_days' => $this->schoolSettings->operationalWeekdays(),
            'available_users' => $this->availableUsers($currentUserId),
        ];
    }

    /**
     * Format employee details for edit form.
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     employee_number: string|null,
     *     phone_number: string|null,
     *     employment_type: string,
     *     position: string|null,
     *     working_days: list<int>|null,
     *     is_active: bool,
     *     is_teacher: bool,
     *     user_id: int|null,
     *     shift_id: int|null
     * }
     */
    public function formatForEdit(Employee $employee): array
    {
        $employee->loadMissing(['user', 'teacher']);

        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'employee_number' => $employee->employee_number,
            'phone_number' => $employee->phone_number,
            'employment_type' => $employee->employment_type->value,
            'position' => $employee->position,
            'working_days' => $employee->working_days,
            'is_active' => $employee->is_active,
            'is_teacher' => $employee->teacher !== null,
            'user_id' => $employee->user_id,
            'shift_id' => $employee->shift_id,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function employmentTypeOptions(): array
    {
        return array_map(fn (EmploymentType $type): array => [
            'value' => $type->value,
            'label' => $type->label(),
        ], EmploymentType::cases());
    }

    /**
     * Shift templates for the employee form select.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function shiftOptions(): array
    {
        return Shift::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Shift $shift): array => ['id' => $shift->id, 'name' => $shift->name])
            ->values()
            ->all();
    }

    /**
     * Available users that can be linked to an employee.
     *
     * @return array<int, array{id: int, username: string}>
     */
    private function availableUsers(?int $currentUserId = null): array
    {
        return User::query()
            ->where(function (Builder $query) use ($currentUserId): void {
                $query->whereDoesntHave('employee');
                if ($currentUserId !== null) {
                    $query->orWhere('id', $currentUserId);
                }
            })
            ->whereDoesntHave('roleGrants', function (Builder $query): void {
                $query->where('role', UserRole::Student->value);
            })
            ->orderBy('username')
            ->get(['id', 'username'])
            ->map(fn (User $u): array => ['id' => $u->id, 'username' => $u->username])
            ->values()
            ->all();
    }
}

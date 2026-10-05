<?php

namespace App\Http\Controllers\Employees;

use App\Enums\EmploymentType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\StoreEmployeeRequest;
use App\Http\Requests\Employees\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\Teacher;
use App\Models\User;
use App\Services\SchoolSettings;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly SchoolSettings $schoolSettings,
    ) {}

    /**
     * Display a listing of employees.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $employees = Employee::query()
            ->with(['user:id,username', 'teacher:id,employee_id'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%")
                        ->orWhere('position', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->through(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'employee_number' => $employee->employee_number,
                'phone_number' => $employee->phone_number,
                'employment_type' => $employee->employment_type->value,
                'employment_type_label' => $employee->employment_type->label(),
                'position' => $employee->position,
                'working_days' => $employee->working_days,
                'is_active' => $employee->is_active,
                'is_teacher' => $employee->teacher !== null,
                'user' => $employee->user ? [
                    'id' => $employee->user->id,
                    'username' => $employee->user->username,
                ] : null,
            ])
            ->withQueryString();

        return Inertia::render('employees/index', [
            'employees' => $employees,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Show the form for creating a new employee.
     */
    public function create(): Response
    {
        return Inertia::render('employees/create', [
            'employment_types' => $this->employmentTypeOptions(),
            'operational_days' => $this->schoolSettings->operationalWeekdays(),
            'available_users' => $this->availableUsers(),
        ]);
    }

    /**
     * Store a newly created employee.
     */
    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $validated = $request->validated();
            $isTeacher = $request->boolean('is_teacher');
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
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Employee created.']);

        return to_route('employees.index');
    }

    /**
     * Show the form for editing the specified employee.
     */
    public function edit(Employee $employee): Response
    {
        $employee->loadMissing(['user', 'teacher']);

        return Inertia::render('employees/edit', [
            'employee' => [
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
            ],
            'employment_types' => $this->employmentTypeOptions(),
            'operational_days' => $this->schoolSettings->operationalWeekdays(),
            'available_users' => $this->availableUsers($employee->user_id),
        ]);
    }

    /**
     * Update the specified employee.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        DB::transaction(function () use ($request, $employee): void {
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

            $validated = $request->validated();
            $isTeacher = $request->boolean('is_teacher');
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
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Employee updated.']);

        return to_route('employees.edit', $employee);
    }

    /**
     * Remove the specified employee.
     */
    public function destroy(Employee $employee): RedirectResponse
    {
        $blockers = $this->deletionBlockers($employee);

        if ($blockers !== []) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $blockers[0]]);

            return back();
        }

        DB::transaction(function () use ($employee): void {
            $old = $employee->toArray();

            $employee->teacher?->delete();
            $employee->delete();

            $employee->recordAudit('deleted', $old, null);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Employee deleted.']);

        return to_route('employees.index');
    }

    /**
     * Reasons the employee cannot be deleted.
     *
     * @return list<string>
     */
    private function deletionBlockers(Employee $employee): array
    {
        $blockers = [];

        if ($employee->isTeacher() && $employee->teacher !== null) {
            if ($employee->teacher->classes()->exists()) {
                $blockers[] = 'Cannot delete: this employee is the homeroom teacher of a class.';
            }

            if ($employee->teacher->classSubjects()->exists()) {
                $blockers[] = 'Cannot delete: this employee has subject teaching assignments.';
            }
        }

        if ($employee->user_id !== null) {
            $blockers[] = 'Cannot delete: linked to a user account. Unlink it first.';
        }

        if ($employee->attendances()->exists()) {
            $blockers[] = 'Cannot delete: this employee has attendance history. Deactivate them instead.';
        }

        return $blockers;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function employmentTypeOptions(): array
    {
        return array_map(fn (EmploymentType $type) => [
            'value' => $type->value,
            'label' => $type->label(),
        ], EmploymentType::cases());
    }

    /**
     * Available users that can be linked to an employee.
     *
     * @return array<int, array{id: int, username: string}>
     */
    private function availableUsers(?int $currentUserId = null): array
    {
        return User::query()
            ->where(function (Builder $query) use ($currentUserId) {
                $query->whereDoesntHave('employee');
                if ($currentUserId !== null) {
                    $query->orWhere('id', $currentUserId);
                }
            })
            ->whereDoesntHave('roleGrants', function (Builder $query) {
                $query->where('role', UserRole::Student->value);
            })
            ->orderBy('username')
            ->get(['id', 'username'])
            ->map(fn (User $u) => ['id' => $u->id, 'username' => $u->username])
            ->values()
            ->all();
    }
}

<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\StoreEmployeeRequest;
use App\Http\Requests\Employees\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Services\Employees\EmployeeService;
use App\Services\Employees\EmployeeViewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employeeService,
        private readonly EmployeeViewService $viewService,
    ) {}

    /**
     * Display a listing of employees.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        return Inertia::render('employees/index', [
            'employees' => $this->viewService->getPaginatedEmployees($search),
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
        return Inertia::render('employees/create', $this->viewService->getFormOptions());
    }

    /**
     * Store a newly created employee.
     */
    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $this->employeeService->createEmployee(
            validated: $request->validated(),
            isTeacher: $request->boolean('is_teacher'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('toasts.employees.created')]);

        return to_route('employees.index');
    }

    /**
     * Show the form for editing the specified employee.
     */
    public function edit(Employee $employee): Response
    {
        return Inertia::render('employees/edit', array_merge(
            ['employee' => $this->viewService->formatForEdit($employee)],
            $this->viewService->getFormOptions($employee->user_id),
        ));
    }

    /**
     * Update the specified employee.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->employeeService->updateEmployee(
            employee: $employee,
            validated: $request->validated(),
            isTeacher: $request->boolean('is_teacher'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('toasts.employees.updated')]);

        return to_route('employees.edit', $employee);
    }

    /**
     * Remove the specified employee.
     */
    public function destroy(Employee $employee): RedirectResponse
    {
        $blockers = $this->employeeService->deletionBlockers($employee);

        if ($blockers !== []) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $blockers[0]]);

            return back();
        }

        $this->employeeService->deleteEmployee($employee);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('toasts.employees.deleted')]);

        return to_route('employees.index');
    }
}

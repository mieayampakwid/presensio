<?php

use App\Http\Controllers\Employees\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('employees/{employee}/edit', [EmployeeController::class, 'edit'])
        ->missing(fn () => to_route('employees.index'))
        ->name('employees.edit');
    Route::put('employees/{employee}', [EmployeeController::class, 'update'])
        ->missing(fn () => to_route('employees.index'))
        ->name('employees.update');
    Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])
        ->missing(fn () => to_route('employees.index'))
        ->name('employees.destroy');
});

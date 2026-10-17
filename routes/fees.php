<?php

use App\Http\Controllers\Fees\DiscountController;
use App\Http\Controllers\Fees\FeeTypeController;
use Illuminate\Support\Facades\Route;

// Read routes: Admin, Finance, Principal
Route::middleware(['auth', 'role:admin,finance,principal'])->group(function () {
    Route::get('fees/types', [FeeTypeController::class, 'index'])->name('fees.types.index');
    Route::get('fees/discounts', [DiscountController::class, 'index'])->name('fees.discounts.index');
});

// Write routes: Admin, Finance only
Route::middleware(['auth', 'role:admin,finance'])->group(function () {
    Route::post('fees/types', [FeeTypeController::class, 'store'])->name('fees.types.store');
    Route::put('fees/types/{fee_type}', [FeeTypeController::class, 'update'])
        ->missing(fn () => to_route('fees.types.index'))
        ->name('fees.types.update');
    Route::delete('fees/types/{fee_type}', [FeeTypeController::class, 'destroy'])
        ->missing(fn () => to_route('fees.types.index'))
        ->name('fees.types.destroy');

    Route::post('fees/discounts', [DiscountController::class, 'store'])->name('fees.discounts.store');
    Route::put('fees/discounts/{discount}', [DiscountController::class, 'update'])
        ->missing(fn () => to_route('fees.discounts.index'))
        ->name('fees.discounts.update');
    Route::delete('fees/discounts/{discount}', [DiscountController::class, 'destroy'])
        ->missing(fn () => to_route('fees.discounts.index'))
        ->name('fees.discounts.destroy');
});

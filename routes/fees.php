<?php

use App\Http\Controllers\Fees\BillController;
use App\Http\Controllers\Fees\BillGenerationController;
use App\Http\Controllers\Fees\CashierController;
use App\Http\Controllers\Fees\DiscountController;
use App\Http\Controllers\Fees\FeeTypeController;
use App\Http\Controllers\Fees\ReceiptController;
use Illuminate\Support\Facades\Route;

// Read routes: Admin, Finance, Principal
Route::middleware(['auth', 'role:admin,finance,principal'])->group(function () {
    Route::get('fees/types', [FeeTypeController::class, 'index'])->name('fees.types.index');
    Route::get('fees/discounts', [DiscountController::class, 'index'])->name('fees.discounts.index');
});

// Kuitansi: Admin, Finance, Principal, and parents (own children only, checked in the controller)
Route::middleware(['auth', 'role:admin,finance,principal,parent'])->group(function () {
    Route::get('fees/payments/{payment}/receipt', [ReceiptController::class, 'show'])->name('fees.payments.receipt');
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

    Route::get('fees/generate', [BillGenerationController::class, 'index'])->name('fees.generate.index');
    Route::post('fees/generate/preview', [BillGenerationController::class, 'preview'])->name('fees.generate.preview');
    Route::post('fees/generate', [BillGenerationController::class, 'store'])->name('fees.generate.store');

    Route::put('fees/bills/{bill}/discount', [BillController::class, 'discount'])->name('fees.bills.discount');
    Route::post('fees/bills/{bill}/cancel', [BillController::class, 'cancel'])->name('fees.bills.cancel');
    Route::post('fees/bills/{bill}/write-off', [BillController::class, 'writeOff'])->name('fees.bills.write-off');

    Route::get('fees/cashier', [CashierController::class, 'index'])->name('fees.cashier.index');
    Route::post('fees/cashier', [CashierController::class, 'store'])->name('fees.cashier.store');
});

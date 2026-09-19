<?php

use App\Http\Controllers\RfidCards\RfidCardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('rfid-cards', [RfidCardController::class, 'index'])->name('rfid-cards.index');
    Route::get('rfid-cards/create', [RfidCardController::class, 'create'])->name('rfid-cards.create');
    Route::post('rfid-cards', [RfidCardController::class, 'store'])->name('rfid-cards.store');
    Route::get('rfid-cards/{rfid_card}/edit', [RfidCardController::class, 'edit'])
        ->missing(fn () => to_route('rfid-cards.index'))
        ->name('rfid-cards.edit');
    Route::put('rfid-cards/{rfid_card}', [RfidCardController::class, 'update'])
        ->missing(fn () => to_route('rfid-cards.index'))
        ->name('rfid-cards.update');
    Route::put('rfid-cards/{rfid_card}/revoke', [RfidCardController::class, 'revoke'])
        ->missing(fn () => to_route('rfid-cards.index'))
        ->name('rfid-cards.revoke');
    Route::delete('rfid-cards/{rfid_card}', [RfidCardController::class, 'destroy'])
        ->missing(fn () => to_route('rfid-cards.index'))
        ->name('rfid-cards.destroy');
});

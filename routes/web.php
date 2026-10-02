<?php

use App\Http\Controllers\CharacterController;
use App\Http\Controllers\PharmacyController;
use App\Http\Middleware\EnsurePlayerHasCharacter;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Unverified players may enter: friends' private server has no mail delivery.
Route::middleware(['auth'])->group(function () {
    Route::get('character/create', [CharacterController::class, 'create'])->name('character.create');
    Route::post('character', [CharacterController::class, 'store'])->name('character.store');

    Route::middleware(EnsurePlayerHasCharacter::class)->group(function () {
        Route::inertia('village', 'village')->name('village');

        Route::get('pharmacy', [PharmacyController::class, 'show'])->name('pharmacy.show');
        Route::post('pharmacy/buy', [PharmacyController::class, 'buy'])->name('pharmacy.buy');
    });
});

require __DIR__.'/settings.php';

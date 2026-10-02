<?php

use App\Http\Controllers\CharacterController;
use App\Http\Middleware\EnsurePlayerHasCharacter;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Unverified players may enter: friends' private server has no mail delivery.
Route::middleware(['auth'])->group(function () {
    Route::get('karakter/buat', [CharacterController::class, 'create'])->name('karakter.create');
    Route::post('karakter', [CharacterController::class, 'store'])->name('karakter.store');

    Route::middleware(EnsurePlayerHasCharacter::class)->group(function () {
        Route::inertia('desa', 'desa')->name('desa');
    });
});

require __DIR__.'/settings.php';

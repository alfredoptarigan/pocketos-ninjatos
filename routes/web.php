<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Unverified players may enter: friends' private server has no mail delivery.
Route::middleware(['auth'])->group(function () {
    Route::inertia('desa', 'desa')->name('desa');
});

require __DIR__.'/settings.php';

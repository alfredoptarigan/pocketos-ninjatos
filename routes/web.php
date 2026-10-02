<?php

use App\Http\Controllers\BagController;
use App\Http\Controllers\BattleController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\GearController;
use App\Http\Controllers\PharmacyController;
use App\Http\Controllers\SkillController;
use App\Http\Controllers\TowerController;
use App\Http\Controllers\VillageController;
use App\Http\Middleware\EnsurePlayerHasCharacter;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Unverified players may enter: friends' private server has no mail delivery.
Route::middleware(['auth'])->group(function () {
    Route::get('character/create', [CharacterController::class, 'create'])->name('character.create');
    Route::post('character', [CharacterController::class, 'store'])->name('character.store');

    Route::middleware(EnsurePlayerHasCharacter::class)->group(function () {
        Route::get('village', [VillageController::class, 'show'])->name('village');
        Route::post('village/travel', [VillageController::class, 'travel'])->name('village.travel');

        Route::get('character', [GearController::class, 'show'])->name('character.show');
        Route::post('character/gear/{gear}/equip', [GearController::class, 'equip'])->name('character.gear.equip');
        Route::post('character/gear/{gear}/unequip', [GearController::class, 'unequip'])->name('character.gear.unequip');
        Route::get('bag', [BagController::class, 'show'])->name('bag');
        Route::post('bag/use', [BagController::class, 'use'])->name('bag.use');

        Route::get('skills', [SkillController::class, 'index'])->name('skills.index');
        Route::post('skills/{skill}/learn', [SkillController::class, 'learn'])->name('skills.learn');

        Route::get('tower', [TowerController::class, 'show'])->name('tower.show');
        Route::post('tower/{floor}/fight', [TowerController::class, 'fight'])->name('tower.fight');
        Route::get('battles/{battle}', [BattleController::class, 'show'])->name('battles.show');

        // Art experiments: original HD sprites next to the vector remake.
        Route::inertia('lab/characters', 'lab/characters')->name('lab.characters');

        Route::get('pharmacy', [PharmacyController::class, 'show'])->name('pharmacy.show');
        Route::post('pharmacy/buy', [PharmacyController::class, 'buy'])->name('pharmacy.buy');
    });
});

require __DIR__.'/settings.php';

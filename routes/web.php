<?php

use App\Http\Controllers\AchievementController;
use App\Http\Controllers\BagController;
use App\Http\Controllers\BattleController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\DungeonController;
use App\Http\Controllers\EquipmentShopController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\GearController;
use App\Http\Controllers\GiftController;
use App\Http\Controllers\OutfitController;
use App\Http\Controllers\PharmacyController;
use App\Http\Controllers\SkillController;
use App\Http\Controllers\TitleController;
use App\Http\Controllers\TowerController;
use App\Http\Controllers\VillageController;
use App\Http\Controllers\WishPotController;
use App\Http\Controllers\WorldController;
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

        // The original game shows the character inside the Inventory.
        Route::get('character', fn () => to_route('bag'))->name('character.show');
        Route::post('character/gear/{gear}/equip', [GearController::class, 'equip'])->name('character.gear.equip');
        Route::post('character/gear/{gear}/unequip', [GearController::class, 'unequip'])->name('character.gear.unequip');
        Route::get('outfits', [OutfitController::class, 'index'])->name('outfits.index');
        Route::get('collection', [CollectionController::class, 'index'])->name('collection.index');
        Route::post('collection/{outfit}/record', [CollectionController::class, 'record'])->name('collection.record');
        Route::get('achievements', [AchievementController::class, 'index'])->name('achievements.index');
        Route::get('titles', [TitleController::class, 'index'])->name('titles.index');
        Route::post('titles/take-off', [TitleController::class, 'takeOff'])->name('titles.take-off');
        Route::post('titles/{title}/wear', [TitleController::class, 'wear'])->name('titles.wear');
        Route::post('outfits/take-off', [OutfitController::class, 'takeOff'])->name('outfits.take-off');
        Route::post('outfits/{outfit}/wear', [OutfitController::class, 'wear'])->name('outfits.wear');
        Route::post('outfits/{outfit}/upgrade', [OutfitController::class, 'upgrade'])->name('outfits.upgrade');
        Route::get('wish-pot', [WishPotController::class, 'show'])->name('wish-pot.show');
        Route::post('wish-pot/{pot}/draw', [WishPotController::class, 'draw'])->name('wish-pot.draw');
        Route::get('bag', [BagController::class, 'show'])->name('bag');
        Route::post('bag/use', [BagController::class, 'use'])->name('bag.use');
        Route::post('bag/sell', [BagController::class, 'sell'])->name('bag.sell');
        Route::get('gifts', [GiftController::class, 'show'])->name('gifts.show');
        Route::post('gifts/sign-in', [GiftController::class, 'signIn'])->name('gifts.sign-in');

        Route::get('skills', [SkillController::class, 'index'])->name('skills.index');
        Route::post('skills/{skill}/learn', [SkillController::class, 'learn'])->name('skills.learn');

        Route::get('world', [WorldController::class, 'show'])->name('world.show');
        Route::get('fields/{field}', [FieldController::class, 'show'])->name('fields.show');
        Route::post('fields/monsters/{monster}/fight', [FieldController::class, 'fight'])->name('fields.fight');
        Route::post('fields/{field}/search/{spot}', [FieldController::class, 'search'])->name('fields.search')->whereIn('spot', ['search', 'cache']);
        Route::get('tower', [TowerController::class, 'show'])->name('tower.show');
        Route::post('tower/{floor}/fight', [TowerController::class, 'fight'])->name('tower.fight');
        Route::get('battles/{battle}', [BattleController::class, 'show'])->name('battles.show');

        Route::get('dungeons', [DungeonController::class, 'index'])->name('dungeons.index');
        Route::get('dungeons/{dungeon}', [DungeonController::class, 'show'])->name('dungeons.show');
        Route::post('dungeons/{dungeon}/enter', [DungeonController::class, 'enter'])->name('dungeons.enter');
        Route::post('dungeon-runs/{run}/fight', [DungeonController::class, 'fight'])->name('dungeon-runs.fight');
        Route::post('dungeon-runs/{run}/leave', [DungeonController::class, 'leave'])->name('dungeon-runs.leave');

        // Art experiments: original HD sprites next to the vector remake.
        Route::inertia('lab/characters', 'lab/characters')->name('lab.characters');

        Route::get('equipment-shop', [EquipmentShopController::class, 'show'])->name('equipment-shop.show');
        Route::post('equipment-shop/{equipment}/buy', [EquipmentShopController::class, 'buy'])->name('equipment-shop.buy');
        Route::post('equipment-shop/gear/{gear}/sell', [EquipmentShopController::class, 'sell'])->name('equipment-shop.sell');
        Route::get('pharmacy', [PharmacyController::class, 'show'])->name('pharmacy.show');
        Route::post('pharmacy/buy', [PharmacyController::class, 'buy'])->name('pharmacy.buy');
    });
});

require __DIR__.'/settings.php';

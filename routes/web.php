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
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HonorController;
use App\Http\Controllers\OutfitController;
use App\Http\Controllers\PharmacyController;
use App\Http\Controllers\SkillController;
use App\Http\Controllers\TitleController;
use App\Http\Controllers\TowerController;
use App\Http\Controllers\VillageController;
use App\Http\Controllers\WishPotController;
use App\Http\Controllers\WorldController;
use App\Http\Middleware\EnsureLocation;
use App\Http\Middleware\EnsurePlayerHasCharacter;
use App\Http\Middleware\GameEntry;
use Illuminate\Support\Facades\Route;

// The whole game lives at "/", like the original client (GameEntry).
Route::get('/', HomeController::class)->name('home');

// Unverified players may enter: friends' private server has no mail delivery.
Route::middleware(['auth'])->group(function () {
    Route::get('character/create', [CharacterController::class, 'create'])->name('character.create');
    Route::post('character', [CharacterController::class, 'store'])->name('character.store');

    Route::middleware([EnsurePlayerHasCharacter::class, GameEntry::class])->group(function () {
        // Menus: open wherever the ninja is.
        // The original game shows the character inside the Inventory.
        Route::get('character', fn () => to_route('bag'))->name('character.show');
        Route::post('character/gear/{gear}/equip', [GearController::class, 'equip'])->name('character.gear.equip');
        Route::post('character/gear/{gear}/unequip', [GearController::class, 'unequip'])->name('character.gear.unequip');
        Route::get('outfits', [OutfitController::class, 'index'])->name('outfits.index');
        Route::get('collection', [CollectionController::class, 'index'])->name('collection.index');
        Route::post('collection/{outfit}/record', [CollectionController::class, 'record'])->name('collection.record');
        Route::get('achievements', [AchievementController::class, 'index'])->name('achievements.index');
        Route::get('honor', [HonorController::class, 'show'])->name('honor.show');
        Route::post('honor/exchange', [HonorController::class, 'exchange'])->name('honor.exchange');
        Route::get('titles', [TitleController::class, 'index'])->name('titles.index');
        Route::post('titles/take-off', [TitleController::class, 'takeOff'])->name('titles.take-off');
        Route::post('titles/{title}/wear', [TitleController::class, 'wear'])->name('titles.wear');
        Route::post('outfits/take-off', [OutfitController::class, 'takeOff'])->name('outfits.take-off');
        Route::post('outfits/{outfit}/wear', [OutfitController::class, 'wear'])->name('outfits.wear');
        Route::post('outfits/{outfit}/upgrade', [OutfitController::class, 'upgrade'])->name('outfits.upgrade');
        Route::get('bag', [BagController::class, 'show'])->name('bag');
        Route::post('bag/use', [BagController::class, 'use'])->name('bag.use');
        Route::post('bag/sell', [BagController::class, 'sell'])->name('bag.sell');
        Route::get('gifts', [GiftController::class, 'show'])->name('gifts.show');
        Route::post('gifts/sign-in', [GiftController::class, 'signIn'])->name('gifts.sign-in');

        Route::get('skills', [SkillController::class, 'index'])->name('skills.index');
        Route::post('skills/reset', [SkillController::class, 'reset'])->name('skills.reset');
        Route::post('skills/equip', [SkillController::class, 'equip'])->name('skills.equip');
        Route::post('skills/unequip', [SkillController::class, 'unequip'])->name('skills.unequip');
        Route::post('skills/page', [SkillController::class, 'page'])->name('skills.page');
        Route::post('skills/slots', [SkillController::class, 'slots'])->name('skills.slots');
        Route::post('skills/{skill}/learn', [SkillController::class, 'learn'])->name('skills.learn');

        Route::get('battles/{battle}', [BattleController::class, 'show'])->name('battles.show');

        // Art experiments: original HD sprites next to the vector remake.
        Route::inertia('lab/characters', 'lab/characters')->name('lab.characters');

        // Leaving a hunting ground or a dungeon: the actions check what is allowed.
        Route::post('village/return', [VillageController::class, 'return'])->name('village.return');
        Route::post('dungeons/exit', [DungeonController::class, 'exit'])->name('dungeons.exit');

        // Places: open only where the ninja is (EnsureLocation), so typing a URL does not move them.
        Route::middleware(EnsureLocation::class.':village')->group(function () {
            Route::get('village', [VillageController::class, 'show'])->name('village');
            Route::get('tower', [TowerController::class, 'show'])->name('tower.show');
            Route::post('tower/{floor}/fight', [TowerController::class, 'fight'])->name('tower.fight')->middleware('throttle:game');
            Route::get('dungeons', [DungeonController::class, 'index'])->name('dungeons.index');
            Route::post('dungeons/{dungeon}/open', [DungeonController::class, 'open'])->name('dungeons.open');
            Route::get('wish-pot', [WishPotController::class, 'show'])->name('wish-pot.show');
            Route::post('wish-pot/{pot}/draw', [WishPotController::class, 'draw'])->name('wish-pot.draw')->middleware('throttle:game');
            Route::get('equipment-shop', [EquipmentShopController::class, 'show'])->name('equipment-shop.show');
            Route::post('equipment-shop/{equipment}/buy', [EquipmentShopController::class, 'buy'])->name('equipment-shop.buy');
            Route::post('equipment-shop/gear/{gear}/sell', [EquipmentShopController::class, 'sell'])->name('equipment-shop.sell');
            Route::get('pharmacy', [PharmacyController::class, 'show'])->name('pharmacy.show');
            Route::post('pharmacy/buy', [PharmacyController::class, 'buy'])->name('pharmacy.buy');
        });

        // The world map is reached from the village or a hunting ground.
        Route::middleware(EnsureLocation::class.':world')->group(function () {
            Route::get('world', [WorldController::class, 'show'])->name('world.show');
            Route::post('village/travel', [VillageController::class, 'travel'])->name('village.travel');
            Route::post('fields/{field}/travel', [FieldController::class, 'travel'])->name('fields.travel');
        });

        Route::middleware(EnsureLocation::class)->group(function () {
            Route::get('fields/{field}', [FieldController::class, 'show'])->name('fields.show');
            Route::post('fields/monsters/{monster}/fight', [FieldController::class, 'fight'])->name('fields.fight')->middleware('throttle:game');
            Route::post('fields/{field}/search/{spot}', [FieldController::class, 'search'])->name('fields.search')->whereIn('spot', ['search', 'cache'])->middleware('throttle:game');

            Route::get('dungeons/{dungeon}', [DungeonController::class, 'show'])->name('dungeons.show');
            Route::post('dungeons/{dungeon}/enter', [DungeonController::class, 'enter'])->name('dungeons.enter');
            Route::post('dungeon-runs/{run}/fight', [DungeonController::class, 'fight'])->name('dungeon-runs.fight')->middleware('throttle:game');
            Route::post('dungeon-runs/{run}/leave', [DungeonController::class, 'leave'])->name('dungeon-runs.leave');
        });
    });
});

require __DIR__.'/settings.php';

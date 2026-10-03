<?php

namespace App\Actions;

use App\Game\OutfitUpgrade;
use App\Models\Character;
use App\Models\Outfit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpgradeOutfit
{
    /**
     * Raise an owned outfit by one level, paying gold and outfit shards.
     *
     * @throws ValidationException when maxed, too young, or short of gold or shards
     */
    public function handle(Character $character, Outfit $outfit): int
    {
        return DB::transaction(function () use ($character, $outfit) {
            $ninja = Character::query()->lockForUpdate()->findOrFail($character->id);
            $owned = $ninja->outfits()->findOrFail($outfit->id);
            $upgrade = OutfitUpgrade::from($owned->pivot->level);

            $error = match (true) {
                $upgrade === null => ['outfit' => "{$outfit->name} is already at the highest level."],
                $ninja->level < $upgrade->characterLevel => ['level' => "+{$upgrade->toLevel} needs character level {$upgrade->characterLevel}."],
                $ninja->gold < $upgrade->gold => ['gold' => "Not enough gold: +{$upgrade->toLevel} costs {$upgrade->gold}."],
                $ninja->outfit_shards < $upgrade->shards => ['shards' => "Not enough outfit shards: +{$upgrade->toLevel} needs {$upgrade->shards}."],
                default => null,
            };

            if ($error !== null) {
                throw ValidationException::withMessages($error);
            }

            $ninja->outfits()->updateExistingPivot($outfit->id, ['level' => $upgrade->toLevel]);
            $ninja->forceFill([
                'gold' => $ninja->gold - $upgrade->gold,
                'outfit_shards' => $ninja->outfit_shards - $upgrade->shards,
            ])->save();

            return $upgrade->toLevel;
        });
    }
}

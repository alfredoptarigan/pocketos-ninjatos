<?php

namespace App\Actions;

use App\Models\Character;
use App\Models\Outfit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordOutfit
{
    /**
     * Record an owned outfit in the avatar collection. It needs the
     * collection's upgrade level and stays in the wardrobe afterwards.
     *
     * @throws ValidationException when not collectible, too low or already recorded
     */
    public function handle(Character $character, Outfit $outfit): void
    {
        DB::transaction(function () use ($character, $outfit) {
            $ninja = Character::query()->lockForUpdate()->findOrFail($character->id);
            $owned = $ninja->outfits()->findOrFail($outfit->id);
            $needed = config('game.collection.record_level');

            $error = match (true) {
                $outfit->collection === null => "{$outfit->name} is not part of the avatar collection.",
                $owned->pivot->recorded_at !== null => "{$outfit->name} is already recorded.",
                $owned->pivot->level < $needed => "Upgrade {$outfit->name} to +{$needed} to record it.",
                default => null,
            };

            if ($error !== null) {
                throw ValidationException::withMessages(['outfit' => $error]);
            }

            $ninja->outfits()->updateExistingPivot($outfit->id, ['recorded_at' => now()]);
        });
    }
}

<?php

namespace App\Actions;

use App\Models\Character;
use App\Models\Dungeon;
use App\Models\DungeonRun;
use App\Models\Field;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Travel
{
    /**
     * Move the ninja to a hunting ground, into a dungeon, or (null) back to the
     * village. The only way a location changes: pages refuse to open elsewhere.
     *
     * @throws ValidationException when the level is too low or a dungeon run is under way
     */
    public function handle(Character $character, Field|Dungeon|null $place): void
    {
        DB::transaction(function () use ($character, $place) {
            $ninja = Character::query()->lockForUpdate()->findOrFail($character->id);
            $run = DungeonRun::query()->whereBelongsTo($ninja)->active()->with('dungeon')->first();

            $problem = match (true) {
                $run !== null && ! ($place instanceof Dungeon && $place->is($run->dungeon)) => "Finish or leave {$run->dungeon->name} first.",
                $place instanceof Field && $ninja->level < $place->level => "{$place->name} needs level {$place->level}.",
                $place instanceof Dungeon && $ninja->level < $place->min_level => "{$place->name} needs level {$place->min_level}.",
                default => null,
            };

            if ($problem !== null) {
                throw ValidationException::withMessages(['location' => $problem]);
            }

            $ninja->forceFill(['location' => Character::locationOf($place)])->save();
        });
    }
}

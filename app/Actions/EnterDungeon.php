<?php

namespace App\Actions;

use App\Models\Character;
use App\Models\Dungeon;
use App\Models\DungeonRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnterDungeon
{
    /**
     * Start a run, or return the one already under way in this dungeon.
     *
     * @throws ValidationException when the ninja may not enter
     */
    public function handle(Character $character, Dungeon $dungeon): DungeonRun
    {
        return DB::transaction(function () use ($character, $dungeon) {
            $ninja = Character::query()->lockForUpdate()->findOrFail($character->id);
            $active = DungeonRun::query()->whereBelongsTo($ninja)->active()->with('dungeon')->first();

            if ($active?->dungeon_id === $dungeon->id) {
                return $active;
            }

            $problem = match (true) {
                $active !== null => "Finish or leave {$active->dungeon->name} first.",
                $ninja->level < $dungeon->min_level => "{$dungeon->name} needs level {$dungeon->min_level}.",
                $dungeon->runsLeftToday($ninja) === 0 => "No runs of {$dungeon->name} left today. Come back tomorrow.",
                default => null,
            };

            if ($problem !== null) {
                throw ValidationException::withMessages(['dungeon' => $problem]);
            }

            return $dungeon->runs()->forceCreate(['character_id' => $ninja->id]);
        });
    }
}

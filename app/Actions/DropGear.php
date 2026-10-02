<?php

namespace App\Actions;

use App\Models\Character;
use App\Models\Equipment;

class DropGear
{
    /**
     * Put a random piece of the best tier an opponent of `$level` allows into the ninja's bag.
     */
    public function handle(Character $ninja, int $level): ?Equipment
    {
        $candidates = Equipment::query()->where('level', '<=', max(1, $level))->get()
            ->groupBy('slot')
            ->flatMap(fn ($pieces) => $pieces->where('level', $pieces->max('level')));

        if ($candidates->isEmpty()) {
            return null;
        }

        $piece = $candidates->random();
        $ninja->gear()->forceCreate(['equipment_id' => $piece->id]);

        return $piece;
    }
}

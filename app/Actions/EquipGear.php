<?php

namespace App\Actions;

use App\Models\Character;
use App\Models\CharacterEquipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EquipGear
{
    /**
     * Wear one of the ninja's pieces, sending whatever was in that slot back to the bag.
     *
     * @throws ValidationException when the ninja's level is too low or the weapon is of another class
     */
    public function handle(Character $character, CharacterEquipment $piece): void
    {
        DB::transaction(function () use ($character, $piece) {
            $ninja = Character::query()->lockForUpdate()->findOrFail($character->id);
            $piece = $ninja->gear()->with('equipment')->lockForUpdate()->findOrFail($piece->id);
            $equipment = $piece->equipment;

            if ($ninja->level < $equipment->level) {
                throw ValidationException::withMessages([
                    'gear' => "{$equipment->name} needs level {$equipment->level}.",
                ]);
            }

            if (! $ninja->canWield($equipment)) {
                $names = config('game.equipment.weapon_classes');

                throw ValidationException::withMessages([
                    'gear' => "{$equipment->name} is a {$names[$equipment->weaponClass()]} weapon; this outfit fights with {$names[$ninja->weaponClass()]}.",
                ]);
            }

            $ninja->gear()->where('equipped_slot', $equipment->slot)->update(['equipped_slot' => null]);
            $piece->forceFill(['equipped_slot' => $equipment->slot])->save();
        });
    }
}

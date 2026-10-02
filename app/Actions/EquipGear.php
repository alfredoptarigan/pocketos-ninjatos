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
     * @throws ValidationException when the ninja's level is too low
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

            $ninja->gear()->where('equipped_slot', $equipment->slot)->update(['equipped_slot' => null]);
            $piece->forceFill(['equipped_slot' => $equipment->slot])->save();
        });
    }
}

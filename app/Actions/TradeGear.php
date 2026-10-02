<?php

namespace App\Actions;

use App\Models\Character;
use App\Models\CharacterEquipment;
use App\Models\Equipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TradeGear
{
    /**
     * Buy a piece from the Equipment Shop into the ninja's bag.
     *
     * @throws ValidationException when the ninja cannot afford it
     */
    public function buy(Character $character, Equipment $equipment): void
    {
        DB::transaction(function () use ($character, $equipment) {
            $ninja = Character::query()->lockForUpdate()->findOrFail($character->id);
            $price = $equipment->buyPrice();

            if ($equipment->level > $ninja->level + config('game.equipment.stock_levels_ahead')) {
                throw ValidationException::withMessages(['gear' => "{$equipment->name} is not sold to ninjas of your level yet."]);
            }

            if ($ninja->gold < $price) {
                throw ValidationException::withMessages(['gear' => "{$equipment->name} costs {$price} gold."]);
            }

            $ninja->forceFill(['gold' => $ninja->gold - $price])->save();
            $ninja->gear()->forceCreate(['equipment_id' => $equipment->id]);
        });
    }

    /**
     * Sell a piece from the bag. Worn gear must be taken off first.
     *
     * @throws ValidationException when the piece is being worn
     */
    public function sell(Character $character, CharacterEquipment $piece): int
    {
        return DB::transaction(function () use ($character, $piece) {
            $ninja = Character::query()->lockForUpdate()->findOrFail($character->id);
            $piece = $ninja->gear()->with('equipment')->lockForUpdate()->findOrFail($piece->id);

            if ($piece->equipped_slot !== null) {
                throw ValidationException::withMessages(['gear' => "Take off {$piece->equipment->name} before selling it."]);
            }

            $price = $piece->equipment->sellPrice();
            $piece->delete();
            $ninja->forceFill(['gold' => $ninja->gold + $price])->save();

            return $price;
        });
    }
}

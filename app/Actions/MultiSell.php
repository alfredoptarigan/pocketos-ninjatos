<?php

namespace App\Actions;

use App\Models\Character;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MultiSell
{
    /**
     * Sell several spare pieces and whole item stacks from the bag at once.
     *
     * @param  list<int>  $gearIds  character_equipment ids
     * @param  list<int>  $itemIds  item ids of stacks in the bag
     * @return array{count: int, gold: int}
     *
     * @throws ValidationException when nothing is picked, or a piece is worn or not the ninja's
     */
    public function handle(Character $character, array $gearIds, array $itemIds): array
    {
        if ($gearIds === [] && $itemIds === []) {
            throw ValidationException::withMessages(['gear' => 'Pick something to sell.']);
        }

        return DB::transaction(function () use ($character, $gearIds, $itemIds) {
            $ninja = Character::query()->lockForUpdate()->findOrFail($character->id);
            $pieces = $ninja->gear()->with('equipment')->whereKey($gearIds)->lockForUpdate()->get();
            $stacks = $ninja->inventory()->with('item')->whereIn('item_id', $itemIds)->lockForUpdate()->get();

            if ($pieces->count() !== count(array_unique($gearIds)) || $stacks->count() !== count(array_unique($itemIds))) {
                throw ValidationException::withMessages(['gear' => 'Some of those are no longer in your bag.']);
            }

            if ($worn = $pieces->firstWhere('equipped_slot', '!==', null)) {
                throw ValidationException::withMessages(['gear' => "Take off {$worn->equipment->name} before selling it."]);
            }

            $gold = $pieces->sum(fn ($piece) => $piece->equipment->sellPrice())
                + $stacks->sum(fn ($stack) => $stack->item->sellPrice() * $stack->quantity);
            $ninja->gear()->whereKey($pieces->modelKeys())->delete();
            $ninja->inventory()->whereKey($stacks->modelKeys())->delete();
            $ninja->forceFill(['gold' => $ninja->gold + $gold])->save();

            return ['count' => $pieces->count() + $stacks->count(), 'gold' => $gold];
        });
    }
}

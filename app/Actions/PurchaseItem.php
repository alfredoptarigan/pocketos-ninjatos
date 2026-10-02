<?php

namespace App\Actions;

use App\Models\Character;
use App\Models\InventoryItem;
use App\Models\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseItem
{
    /**
     * Pay for `$quantity` of `$item` and add them to the character's stack.
     *
     * The character row is locked so two simultaneous purchases cannot
     * both spend the same gold.
     *
     * @throws ValidationException when the player cannot afford or carry them
     */
    public function handle(Character $character, Item $item, int $quantity): InventoryItem
    {
        return DB::transaction(function () use ($character, $item, $quantity) {
            $buyer = Character::query()->lockForUpdate()->findOrFail($character->id);
            $stack = $buyer->inventory()->where('item_id', $item->id)->lockForUpdate()->first();
            $owned = $stack->quantity ?? 0;
            $cost = $item->price * $quantity;

            if ($owned + $quantity > $item->max_stack) {
                throw ValidationException::withMessages([
                    'quantity' => "Tas hanya muat {$item->max_stack} {$item->name}. Kamu sudah punya {$owned}.",
                ]);
            }

            if ($cost > $buyer->gold) {
                throw ValidationException::withMessages([
                    'quantity' => "Koin tidak cukup: butuh {$cost}, kamu punya {$buyer->gold}.",
                ]);
            }

            $buyer->forceFill(['gold' => $buyer->gold - $cost])->save();

            if ($stack) {
                $stack->update(['quantity' => $owned + $quantity]);

                return $stack;
            }

            return $buyer->inventory()->create(['item_id' => $item->id, 'quantity' => $quantity]);
        });
    }
}

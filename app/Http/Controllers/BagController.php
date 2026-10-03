<?php

namespace App\Http\Controllers;

use App\Http\Requests\UseItemRequest;
use App\Models\CharacterEquipment;
use App\Models\InventoryItem;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BagController extends Controller
{
    /**
     * The Inventory, as in the original: worn gear around the ninja, stats,
     * and a bag of spare gear and item stacks.
     */
    public function show(Request $request): Response
    {
        $character = $request->user()->character;
        $stacks = $character->inventory()->with('item')->orderBy('item_id')->get();
        $pieces = $character->gear()->with('equipment')->get()
            ->sortBy([fn (CharacterEquipment $piece) => $piece->equipment->slot, fn (CharacterEquipment $piece) => -$piece->equipment->level]);
        $describe = fn (CharacterEquipment $piece) => ['id' => $piece->id, ...$piece->equipment->summary()];

        return Inertia::render('inventory', [
            'stats' => (array) $character->stats(),
            'slots' => config('game.equipment.slots'),
            'worn' => $pieces->whereNotNull('equipped_slot')->keyBy('equipped_slot')->map($describe),
            'gear' => $pieces->whereNull('equipped_slot')->values()->map($describe),
            // Only created avatars have create-screen portraits; outfits show their sprite.
            'hasPortrait' => $character->outfit_id === null,
            'items' => $stacks->map(fn (InventoryItem $stack) => [
                'id' => $stack->item->id,
                'name' => $stack->item->name,
                'icon' => $stack->item->icon,
                'quantity' => $stack->quantity,
                'max_stack' => $stack->item->max_stack,
                'restore_hp' => $stack->item->restore_hp,
                'restore_chakra' => $stack->item->restore_chakra,
                'restore_energy' => $stack->item->restore_energy,
            ]),
        ]);
    }

    /**
     * Drink a potion: restore health/chakra (capped) and use up one from the stack.
     */
    public function use(UseItemRequest $request): RedirectResponse
    {
        $item = Item::findOrFail($request->integer('item_id'));

        DB::transaction(function () use ($request, $item) {
            $character = $request->user()->character()->lockForUpdate()->firstOrFail();
            $stack = $character->inventory()->where('item_id', $item->id)->lockForUpdate()->firstOrFail();
            $stats = $character->stats();

            $character->setVitals(
                min($stats->maxHp, $character->currentHp() + $item->restore_hp),
                min($stats->maxMp, $character->currentMp() + $item->restore_chakra),
            );
            $character->save();

            $stack->quantity > 1 ? $stack->decrement('quantity') : $stack->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Used {$item->name}."]);

        return to_route('bag');
    }
}

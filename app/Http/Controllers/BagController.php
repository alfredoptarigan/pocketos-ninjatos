<?php

namespace App\Http\Controllers;

use App\Http\Requests\UseItemRequest;
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
     * Show everything the player is carrying.
     */
    public function show(Request $request): Response
    {
        $stacks = $request->user()->character->inventory()->with('item')->orderBy('item_id')->get();

        return Inertia::render('bag', [
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

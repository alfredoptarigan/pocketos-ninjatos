<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use Illuminate\Http\Request;
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
}

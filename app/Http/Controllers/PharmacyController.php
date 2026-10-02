<?php

namespace App\Http\Controllers;

use App\Actions\PurchaseItem;
use App\Http\Requests\BuyItemRequest;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PharmacyController extends Controller
{
    /**
     * Show the pharmacy stock and how many of each the player carries.
     */
    public function show(Request $request): Response
    {
        $character = $request->user()->character;

        return Inertia::render('buildings/pharmacy', [
            'items' => Item::query()->pharmacy()->orderBy('code')->get([
                'id', 'name', 'icon', 'price', 'restore_hp', 'restore_chakra', 'restore_energy', 'max_stack',
            ]),
            'owned' => $character->inventory()->pluck('quantity', 'item_id'),
        ]);
    }

    /**
     * Buy a stack of potions.
     */
    public function buy(BuyItemRequest $request, PurchaseItem $purchase): RedirectResponse
    {
        $item = Item::findOrFail($request->integer('item_id'));
        $quantity = $request->integer('quantity');

        $purchase->handle($request->user()->character, $item, $quantity);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Bought {$quantity} {$item->name}."]);

        return to_route('pharmacy.show');
    }
}

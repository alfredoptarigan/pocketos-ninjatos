<?php

namespace App\Http\Controllers;

use App\Actions\TradeGear;
use App\Models\CharacterEquipment;
use App\Models\Equipment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EquipmentShopController extends Controller
{
    /**
     * The shop's stock (basic tiers up to a few levels ahead) and the gear the ninja could sell.
     */
    public function show(Request $request): Response
    {
        $character = $request->user()->character;
        $describe = fn (Equipment $equipment) => [
            ...$equipment->summary(),
            'id' => $equipment->id,
            'buy_price' => $equipment->buyPrice(),
            'sell_price' => $equipment->sellPrice(),
        ];

        return Inertia::render('buildings/equipment-shop', [
            'stock' => Equipment::query()
                ->where('level', '<=', $character->level + config('game.equipment.stock_levels_ahead'))
                ->orderBy('level')->orderBy('code')->get()->map($describe),
            'bag' => $character->gear()->whereNull('equipped_slot')->with('equipment')->get()
                ->map(fn (CharacterEquipment $piece) => [...$describe($piece->equipment), 'id' => $piece->id]),
        ]);
    }

    public function buy(Request $request, Equipment $equipment, TradeGear $trade): RedirectResponse
    {
        $trade->buy($request->user()->character, $equipment);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Bought {$equipment->name}. It is in your bag."]);

        return to_route('equipment-shop.show');
    }

    public function sell(Request $request, CharacterEquipment $gear, TradeGear $trade): RedirectResponse
    {
        // Scoped to the player's own gear: other players' pieces are a 404.
        $name = $request->user()->character->gear()->with('equipment')->findOrFail($gear->id)->equipment->name;
        $gold = $trade->sell($request->user()->character, $gear);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Sold {$name} for {$gold} gold."]);

        return to_route('equipment-shop.show');
    }
}

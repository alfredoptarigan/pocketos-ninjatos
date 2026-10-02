<?php

namespace App\Http\Controllers;

use App\Actions\EquipGear;
use App\Models\CharacterEquipment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GearController extends Controller
{
    /**
     * The character panel: stats, worn gear and the gear in the bag.
     */
    public function show(Request $request): Response
    {
        $character = $request->user()->character;
        $pieces = $character->gear()->with('equipment')->get()
            ->sortBy([fn (CharacterEquipment $piece) => $piece->equipment->slot, fn (CharacterEquipment $piece) => -$piece->equipment->level]);
        $describe = fn (CharacterEquipment $piece) => ['id' => $piece->id, ...$piece->equipment->summary()];

        return Inertia::render('character/show', [
            'stats' => (array) $character->stats(),
            'slots' => config('game.equipment.slots'),
            'worn' => $pieces->whereNotNull('equipped_slot')->keyBy('equipped_slot')->map($describe),
            'bag' => $pieces->whereNull('equipped_slot')->values()->map($describe),
        ]);
    }

    public function equip(Request $request, CharacterEquipment $gear, EquipGear $equip): RedirectResponse
    {
        $equip->handle($request->user()->character, $gear);

        return to_route('character.show');
    }

    public function unequip(Request $request, CharacterEquipment $gear): RedirectResponse
    {
        // Scoped to the player's own gear: other players' pieces are a 404.
        $request->user()->character->gear()->findOrFail($gear->id)
            ->forceFill(['equipped_slot' => null])->save();

        return to_route('character.show');
    }
}

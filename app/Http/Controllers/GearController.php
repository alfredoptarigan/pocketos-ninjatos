<?php

namespace App\Http\Controllers;

use App\Actions\EquipGear;
use App\Models\CharacterEquipment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Wearing and taking off gear; both happen in the Inventory (BagController).
 */
class GearController extends Controller
{
    public function equip(Request $request, CharacterEquipment $gear, EquipGear $equip): RedirectResponse
    {
        $equip->handle($request->user()->character, $gear);

        return to_route('bag');
    }

    public function unequip(Request $request, CharacterEquipment $gear): RedirectResponse
    {
        // Scoped to the player's own gear: other players' pieces are a 404.
        $request->user()->character->gear()->findOrFail($gear->id)
            ->forceFill(['equipped_slot' => null])->save();

        return to_route('bag');
    }
}

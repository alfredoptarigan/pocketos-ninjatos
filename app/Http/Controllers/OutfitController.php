<?php

namespace App\Http\Controllers;

use App\Models\Outfit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OutfitController extends Controller
{
    private const RARITY_ORDER = ['orange', 'blue', 'grey'];

    /**
     * The wardrobe: owned outfits, best rarity first.
     */
    public function index(Request $request): Response
    {
        $character = $request->user()->character;

        return Inertia::render('character/outfits', [
            'outfits' => $character->outfits()->orderBy('name')->get()
                ->sortBy(fn (Outfit $outfit) => array_search($outfit->rarity, self::RARITY_ORDER, true))
                ->values()
                ->map(fn (Outfit $outfit) => $outfit->summary()),
            'worn' => $character->outfit_id,
            'avatar' => $character->avatar,
        ]);
    }

    public function wear(Request $request, Outfit $outfit): RedirectResponse
    {
        $character = $request->user()->character;
        // Only outfits in the ninja's own wardrobe: others are a 404.
        $owned = $character->outfits()->findOrFail($outfit->id);
        $character->forceFill(['outfit_id' => $owned->id])->save();

        return to_route('outfits.index');
    }

    public function takeOff(Request $request): RedirectResponse
    {
        $request->user()->character->forceFill(['outfit_id' => null])->save();

        return to_route('outfits.index');
    }
}

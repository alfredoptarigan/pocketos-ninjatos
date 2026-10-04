<?php

namespace App\Http\Controllers;

use App\Actions\UpgradeOutfit;
use App\Game\OutfitUpgrade;
use App\Models\Character;
use App\Models\Outfit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OutfitController extends Controller
{
    private const RARITY_ORDER = ['orange', 'blue', 'grey'];

    /**
     * The wardrobe: owned outfits, best rarity first, with their next upgrade.
     */
    public function index(Request $request): Response
    {
        $character = $request->user()->character;

        $levels = $character->outfits()->pluck('character_outfits.level', 'outfits.id');

        return Inertia::render('character/outfits', [
            'outfits' => $character->outfits()->orderBy('name')->get()
                ->sortBy(fn (Outfit $outfit) => array_search($outfit->rarity, self::RARITY_ORDER, true))
                ->values()
                ->map(fn (Outfit $outfit) => [
                    ...$outfit->summary($levels[$outfit->id]),
                    'upgrade' => OutfitUpgrade::from($levels[$outfit->id]),
                ]),
            'worn' => $character->outfit_id,
            'shards' => $character->outfit_shards,
            'avatar' => $character->avatar,
        ]);
    }

    public function wear(Request $request, Outfit $outfit): RedirectResponse
    {
        $character = $request->user()->character;
        // Only outfits in the ninja's own wardrobe: others are a 404.
        $owned = $character->outfits()->findOrFail($outfit->id);
        $character->forceFill(['outfit_id' => $owned->id])->save();
        $this->dropUnfitWeapon($character);

        return to_route('outfits.index');
    }

    public function upgrade(Request $request, Outfit $outfit, UpgradeOutfit $upgrade): RedirectResponse
    {
        $upgrade->handle($request->user()->character, $outfit);

        return to_route('outfits.index');
    }

    public function takeOff(Request $request): RedirectResponse
    {
        $character = $request->user()->character;
        $character->forceFill(['outfit_id' => null])->save();
        $this->dropUnfitWeapon($character);

        return to_route('outfits.index');
    }

    private function dropUnfitWeapon(Character $character): void
    {
        $dropped = $character->refresh()->dropUnfitWeapon();

        if ($dropped) {
            Inertia::flash('toast', ['type' => 'info', 'message' => "{$dropped} went back to the bag: this outfit fights with other weapons."]);
        }
    }
}

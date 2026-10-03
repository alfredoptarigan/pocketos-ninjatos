<?php

namespace App\Http\Controllers;

use App\Actions\DrawWishPot;
use App\Models\Outfit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class WishPotController extends Controller
{
    /**
     * The Lucky Pot building: Wishing Pots for sale with their odds, or for
     * pick pots the outfits the ninja may choose (marking those owned).
     */
    public function show(Request $request): Response
    {
        $character = $request->user()->character;
        $owned = $character->outfits()->pluck('outfits.id');

        return Inertia::render('buildings/wish-pot', [
            'pots' => collect(config('game.outfits.pots'))
                ->map(fn (array $pot, string $key) => [
                    'key' => $key,
                    ...Arr::except($pot, 'pick'),
                    'choices' => isset($pot['pick'])
                        ? Outfit::query()->whereIn('key', $pot['pick'])->where('sex', $character->sex())->get()
                            ->map(fn (Outfit $outfit) => [...$outfit->summary(), 'owned' => $owned->contains($outfit->id)])
                        : null,
                ])
                ->values(),
        ]);
    }

    /**
     * Open one pot; the page reveals the outfit from the flashed `drawn`.
     */
    public function draw(Request $request, string $pot, DrawWishPot $draw): RedirectResponse
    {
        // Looked up by key, not dot path: "ninja.odds" must not reach inside a pot.
        $config = config('game.outfits.pots')[$pot] ?? abort(404);
        $result = $draw->handle($request->user()->character, $config, $request->string('outfit')->toString() ?: null);

        Inertia::flash('drawn', [
            'outfit' => $result['outfit']->summary(),
            'duplicate' => $result['duplicate'],
            'shards' => $result['shards'],
        ]);

        return to_route('wish-pot.show');
    }
}

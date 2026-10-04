<?php

namespace App\Http\Controllers;

use App\Actions\DrawWishPot;
use App\Models\Outfit;
use App\Models\Title;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class WishPotController extends Controller
{
    /**
     * The Lucky Pot building: Wishing Pots for sale with their odds, the
     * outfits a pick or pool pot offers (marking those owned, at their level),
     * the titles of a title box, and locked pots with what they wait for.
     */
    public function show(Request $request): Response
    {
        $character = $request->user()->character;
        // Outfit id => upgrade level owned.
        $owned = $character->outfits()->pluck('character_outfits.level', 'outfits.id');
        $titles = $character->titles()->pluck('titles.code');

        return Inertia::render('buildings/wish-pot', [
            'pots' => collect(config('game.outfits.pots'))
                ->map(fn (array $pot, string $key) => [
                    'key' => $key,
                    ...Arr::except($pot, ['pick', 'pool', 'titles']),
                    'pickable' => isset($pot['pick']),
                    'choices' => isset($pot['pick']) || isset($pot['pool'])
                        ? Outfit::query()->whereIn('key', $pot['pick'] ?? $pot['pool'])->where('sex', $character->sex())->get()
                            ->map(fn (Outfit $outfit) => [
                                ...$outfit->summary((int) ($owned[$outfit->id] ?? 0)),
                                'owned' => $owned->has($outfit->id),
                            ])
                            ->values()
                        : null,
                    'titles' => isset($pot['titles'])
                        ? Title::query()->whereIn('code', $pot['titles'])->get()
                            ->map(fn (Title $title) => [...$title->summary(), 'owned' => $titles->contains($title->code)])
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

        Inertia::flash('drawn', isset($result['title']) ? ['title' => $result['title']->summary()] : [
            'outfit' => $result['outfit']->summary($result['level']),
            'duplicate' => $result['duplicate'],
            'shards' => $result['shards'],
        ]);

        return to_route('wish-pot.show');
    }
}

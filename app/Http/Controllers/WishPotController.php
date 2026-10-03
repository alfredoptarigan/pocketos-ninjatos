<?php

namespace App\Http\Controllers;

use App\Actions\DrawWishPot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WishPotController extends Controller
{
    /**
     * The Lucky Pot building: Wishing Pots for sale and their odds.
     */
    public function show(): Response
    {
        return Inertia::render('buildings/wish-pot', [
            'pots' => collect(config('game.outfits.pots'))
                ->map(fn (array $pot, string $key) => ['key' => $key, ...$pot])
                ->values(),
        ]);
    }

    /**
     * Open one pot; the page reveals the outfit from the flashed `drawn`.
     */
    public function draw(Request $request, string $pot, DrawWishPot $draw): RedirectResponse
    {
        $config = config("game.outfits.pots.{$pot}") ?? abort(404);
        $result = $draw->handle($request->user()->character, $config);

        Inertia::flash('drawn', [
            'outfit' => $result['outfit']->summary(),
            'duplicate' => $result['duplicate'],
            'gold' => $result['gold'],
        ]);

        return to_route('wish-pot.show');
    }
}

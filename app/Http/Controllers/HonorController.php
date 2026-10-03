<?php

namespace App\Http\Controllers;

use App\Actions\ExchangeHonor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HonorController extends Controller
{
    /**
     * My Honor: rank, medals and the Honor Exchange.
     */
    public function show(Request $request): Response
    {
        $character = $request->user()->character;
        $honor = config('game.honor');

        return Inertia::render('character/honor', [
            'honor' => $character->honor,
            'medals' => $character->medals,
            'rank' => $character->honorRank(),
            'perRank' => $honor['per_rank'],
            'exchangesLeft' => $character->honorExchangesLeft(),
            'ranks' => array_map(fn (array $rank) => ['medals' => $rank[0], 'exp' => $rank[1]], $honor['exchange']),
            'sources' => ['tower' => $honor['tower_first_clear'], 'dungeon' => $honor['dungeon_clear']],
        ]);
    }

    public function exchange(Request $request, ExchangeHonor $exchange): RedirectResponse
    {
        $result = $exchange->handle($request->user()->character);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Exchanged {$result['medals']} medals for {$result['exp']} EXP."]);

        return to_route('honor.show');
    }
}

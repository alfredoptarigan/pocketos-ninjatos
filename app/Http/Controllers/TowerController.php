<?php

namespace App\Http\Controllers;

use App\Actions\ChallengeTowerFloor;
use App\Models\TowerFloor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TowerController extends Controller
{
    /**
     * Show the Training Tower floors and the player's progress.
     */
    public function show(Request $request): Response
    {
        $character = $request->user()->character;

        return Inertia::render('tower', [
            'floors' => TowerFloor::query()->orderBy('floor')->get([
                'floor', 'name', 'is_boss', 'level', 'max_hp', 'min_atk', 'max_atk', 'defense', 'exp', 'art',
            ]),
            'cleared' => $character->tower_floor,
            'stats' => $character->stats(),
        ]);
    }

    /**
     * Fight a floor: the next uncleared one, or any cleared floor again.
     */
    public function fight(Request $request, TowerFloor $floor, ChallengeTowerFloor $challenge): RedirectResponse
    {
        $character = $request->user()->character;

        abort_if($floor->floor > $character->tower_floor + 1, 403, 'Clear the floors below first.');

        $battle = $challenge->handle($character, $floor);

        return to_route('battles.show', $battle);
    }
}

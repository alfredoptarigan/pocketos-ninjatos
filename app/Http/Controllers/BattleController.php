<?php

namespace App\Http\Controllers;

use App\Models\Battle;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BattleController extends Controller
{
    /**
     * Replay one of the player's own battles.
     */
    public function show(Request $request, Battle $battle): Response
    {
        // Other players' battles are not ours to show; do not reveal they exist.
        abort_unless($battle->character_id === $request->user()->character->id, 404);

        return Inertia::render('battle', [
            'battle' => $battle->only(['id', 'floor', 'won', 'log', 'rewards']),
        ]);
    }
}

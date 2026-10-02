<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCharacterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CharacterController extends Controller
{
    /**
     * Show the create-your-ninja screen.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user()->character()->exists()) {
            return to_route('village');
        }

        return Inertia::render('character/create', [
            'avatars' => config('game.avatars'),
        ]);
    }

    /**
     * Create the player's ninja.
     */
    public function store(StoreCharacterRequest $request): RedirectResponse
    {
        if ($request->user()->character()->exists()) {
            return to_route('village');
        }

        // gold and village are not mass assignable: only game actions change them.
        $request->user()->character()->make($request->validated())
            ->forceFill([
                'gold' => config('game.starting_gold'),
                'village' => config('game.home_village'),
            ])
            ->save();

        return to_route('village');
    }
}

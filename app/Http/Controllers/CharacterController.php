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
            return to_route('desa');
        }

        return Inertia::render('karakter/buat', [
            'avatars' => config('game.avatars'),
        ]);
    }

    /**
     * Create the player's ninja.
     */
    public function store(StoreCharacterRequest $request): RedirectResponse
    {
        if ($request->user()->character()->exists()) {
            return to_route('desa');
        }

        // gold is not mass assignable: it only changes through game actions.
        $request->user()->character()->make($request->validated())
            ->forceFill(['gold' => config('game.starting_gold')])
            ->save();

        return to_route('desa');
    }
}

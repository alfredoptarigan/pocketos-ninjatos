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

        $request->user()->character()->create($request->validated());

        return to_route('desa');
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\Travel;
use App\Http\Requests\TravelRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VillageController extends Controller
{
    /**
     * Show the village the player is currently in.
     */
    public function show(Request $request): Response
    {
        $villages = config()->array('game.villages');
        $current = $request->user()->character->village;

        return Inertia::render('village', [
            'village' => ['id' => $current, 'name' => $villages[$current]],
            'villages' => collect($villages)
                ->map(fn (string $name, string $id) => ['id' => (string) $id, 'name' => $name])
                ->values(),
        ]);
    }

    /**
     * Move the player to another village.
     */
    public function travel(TravelRequest $request, Travel $travel): RedirectResponse
    {
        $village = $request->string('village')->toString();

        $travel->handle($request->user()->character, null);
        $request->user()->character->forceFill(['village' => $village])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Arrived at '.config()->array('game.villages')[$village].'.']);

        return to_route('village');
    }

    /**
     * Walk back from a hunting ground to the village.
     */
    public function return(Request $request, Travel $travel): RedirectResponse
    {
        $travel->handle($request->user()->character, null);

        return to_route('village');
    }
}

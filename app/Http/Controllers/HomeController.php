<?php

namespace App\Http\Controllers;

use App\Models\Dungeon;
use App\Models\Field;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * "/": the welcome page for guests, otherwise the place the ninja is in.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        if ($request->user() === null) {
            return Inertia::render('welcome');
        }

        $character = $request->user()->character;

        if ($character === null) {
            return to_route('character.create');
        }

        return match (true) {
            ($place = $character->place()) instanceof Field => app()->call([app(FieldController::class), 'show'], ['field' => $place]),
            $place instanceof Dungeon => app()->call([app(DungeonController::class), 'show'], ['dungeon' => $place]),
            default => app()->call([app(VillageController::class), 'show']),
        };
    }
}

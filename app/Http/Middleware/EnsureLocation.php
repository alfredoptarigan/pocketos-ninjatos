<?php

namespace App\Http\Middleware;

use App\Models\Character;
use App\Models\Dungeon;
use App\Models\DungeonRun;
use App\Models\Field;
use App\Models\FieldMonster;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureLocation
{
    /**
     * Places open only where the ninja is, so typing a URL does not move them:
     * "village" for the village buildings, "world" for the map (village or a
     * hunting ground), "here" for the field or dungeon named in the route.
     * Anything else sends them back to where they are.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $scope = 'here'): Response
    {
        /** @var Character $character */
        $character = $request->user()->character;
        $location = $character->location;

        $allowed = match ($scope) {
            'village' => $location === null,
            'world' => $location === null || str_starts_with($location, 'field:'),
            default => $location !== null && $location === $this->routeLocation($request),
        };

        if ($allowed) {
            return $next($request);
        }

        // Menus close to the village: from a field or dungeon that just means "back to the game".
        if (! $request->routeIs('village')) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'You cannot go there from here. Travel through the game.']);
        }

        return redirect($character->locationUrl());
    }

    /**
     * The field or dungeon the route acts on.
     */
    private function routeLocation(Request $request): ?string
    {
        $route = $request->route();

        return match (true) {
            $route->parameter('field') instanceof Field => Character::locationOf($route->parameter('field')),
            $route->parameter('monster') instanceof FieldMonster => "field:{$route->parameter('monster')->field_scene}",
            $route->parameter('dungeon') instanceof Dungeon => Character::locationOf($route->parameter('dungeon')),
            $route->parameter('run') instanceof DungeonRun => "dungeon:{$route->parameter('run')->dungeon_id}",
            default => null,
        };
    }
}

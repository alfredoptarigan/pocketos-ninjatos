<?php

namespace App\Http\Controllers;

use App\Actions\TrackAchievements;
use App\Models\Achievement;
use App\Models\Title;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AchievementController extends Controller
{
    /**
     * Every tracked achievement with the ninja's progress. Opening the page
     * also catches up on achievements added after the ninja passed them.
     */
    public function index(Request $request, TrackAchievements $track): Response
    {
        $character = $request->user()->character;
        $track->handle($character);
        $counters = $track->counters($character);
        $completed = $character->achievements()->get()->keyBy('id');
        $titles = Title::query()->pluck('name', 'code');

        return Inertia::render('character/achievements', [
            'achievements' => Achievement::query()->orderBy('id')->get()->map(fn (Achievement $achievement) => [
                'id' => $achievement->id,
                'name' => $achievement->name,
                'goal' => $achievement->goal(),
                'target' => $achievement->target,
                'progress' => min($counters[$achievement->counter], $achievement->target),
                'points' => $achievement->points,
                'title' => $titles[$achievement->title] ?? null,
                'completed_at' => $completed->get($achievement->id)?->pivot->completed_at,
            ]),
            'points' => $counters['points'],
        ]);
    }
}

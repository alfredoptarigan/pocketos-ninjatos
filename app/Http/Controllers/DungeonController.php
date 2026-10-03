<?php

namespace App\Http\Controllers;

use App\Actions\EnterDungeon;
use App\Actions\FightDungeonWave;
use App\Models\Dungeon;
use App\Models\DungeonRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DungeonController extends Controller
{
    private const DIFFICULTY_ORDER = ['trial', 'normal', 'hard'];

    /**
     * Every dungeon by level, with today's runs left and the run under way.
     */
    public function index(Request $request): Response
    {
        $character = $request->user()->character;

        return Inertia::render('dungeons/index', [
            'dungeons' => Dungeon::query()->get()
                ->sortBy([['min_level', 'asc'], fn (Dungeon $a, Dungeon $b) => array_search($a->difficulty, self::DIFFICULTY_ORDER) <=> array_search($b->difficulty, self::DIFFICULTY_ORDER)])
                ->values()
                ->map(fn (Dungeon $dungeon) => [
                    ...$dungeon->only(['id', 'name', 'difficulty', 'min_level', 'max_level', 'picture', 'daily_runs', 'reward_exp', 'reward_gold']),
                    'stages' => count($dungeon->stages),
                    'runs_left' => $dungeon->runsLeftToday($character),
                ]),
            'active' => $this->activeRun($request)?->dungeon_id,
        ]);
    }

    /**
     * One dungeon: its stages and wave leaders, and how far the current run got.
     */
    public function show(Request $request, Dungeon $dungeon): Response
    {
        $run = $this->activeRun($request);

        return Inertia::render('dungeons/show', [
            'dungeon' => [
                ...$dungeon->only(['id', 'name', 'difficulty', 'min_level', 'max_level', 'picture', 'reward_exp', 'reward_gold']),
                'stages' => collect($dungeon->stages)->map(fn (array $stage) => [
                    ...collect($stage)->only(['name', 'recommended', 'reward_exp', 'reward_gold']),
                    'waves' => collect($stage['waves'])->map(fn (array $wave) => [
                        ...collect($wave)->only(['name', 'is_boss', 'level', 'max_hp', 'min_atk', 'max_atk', 'defense', 'exp']),
                        'face' => $wave['art']['face'],
                    ]),
                ]),
            ],
            'runs_left' => $dungeon->runsLeftToday($request->user()->character),
            'run' => $run?->only(['id', 'dungeon_id', 'stage', 'wave']),
        ]);
    }

    public function enter(Request $request, Dungeon $dungeon, EnterDungeon $enter): RedirectResponse
    {
        $enter->handle($request->user()->character, $dungeon);

        return to_route('dungeons.show', $dungeon);
    }

    public function fight(Request $request, DungeonRun $run, FightDungeonWave $fight): RedirectResponse
    {
        $character = $request->user()->character;
        // Other players' runs are not ours to fight; do not reveal they exist.
        abort_unless($run->character_id === $character->id, 404);

        return to_route('battles.show', $fight->handle($character, $run));
    }

    public function leave(Request $request, DungeonRun $run): RedirectResponse
    {
        abort_unless($run->character_id === $request->user()->character->id, 404);
        DungeonRun::query()->whereKey($run->id)->active()->update(['status' => 'left']);

        return to_route('dungeons.index');
    }

    private function activeRun(Request $request): ?DungeonRun
    {
        return DungeonRun::query()->whereBelongsTo($request->user()->character)->active()->first();
    }
}

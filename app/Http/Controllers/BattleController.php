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
            'battle' => [
                ...$battle->only(['id', 'floor', 'won', 'log', 'rewards']),
                // Hunting-ground battles lead back to their area; tower battles to the tower.
                'field' => $battle->fieldMonster ? [
                    'scene' => $battle->fieldMonster->field_scene,
                    'name' => $battle->fieldMonster->field->name,
                    'monster' => $battle->fieldMonster->id,
                ] : null,
                // Dungeon waves lead back to the dungeon, or on to the next wave.
                'dungeon' => $battle->dungeonRun ? [
                    'id' => $battle->dungeonRun->dungeon_id,
                    'name' => $battle->dungeonRun->dungeon->name,
                    'run' => $battle->dungeonRun->id,
                    'active' => $battle->dungeonRun->status === 'active',
                ] : null,
            ],
            // Names, icons, schools (for the sound) and descriptions (status tooltips) of the jutsu the replay may show.
            'skills' => collect(config('skills.skills'))->map(fn (array $skill, string|int $id) => [
                'name' => $skill['name'],
                'school' => $skill['school'],
                'description' => $skill['description'] ?? '',
                'icon' => "/game-assets/skills/$id.png",
            ]),
        ]);
    }
}

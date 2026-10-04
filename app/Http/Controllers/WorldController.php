<?php

namespace App\Http\Controllers;

use App\Models\Field;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorldController extends Controller
{
    /**
     * The world map: villages and hunting grounds.
     */
    public function show(Request $request): Response
    {
        return Inertia::render('world', [
            'fields' => Field::query()->with('monsters:id,field_scene,name,level,is_boss')->orderBy('scene')->get()
                ->map(fn (Field $field) => [
                    ...$field->only(['scene', 'name', 'village', 'level']),
                    'monsters' => $field->monsters->map->only(['name', 'level', 'is_boss']),
                ]),
            'villages' => collect(config()->array('game.villages'))
                ->map(fn (string $name, string|int $id) => ['id' => (string) $id, 'name' => $name])
                ->values(),
        ]);
    }
}

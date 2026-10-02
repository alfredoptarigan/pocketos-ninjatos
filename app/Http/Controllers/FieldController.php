<?php

namespace App\Http\Controllers;

use App\Actions\HuntMonster;
use App\Models\Field;
use App\Models\FieldMonster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FieldController extends Controller
{
    /**
     * A hunting ground with its monsters.
     */
    public function show(Request $request, Field $field): Response|RedirectResponse
    {
        if ($request->user()->character->level < $field->level) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "{$field->name} needs level {$field->level}."]);

            return to_route('world.show');
        }

        return Inertia::render('field', [
            'field' => $field->only(['scene', 'name', 'level', 'background']),
            'monsters' => $field->monsters()->get(['id', 'name', 'is_boss', 'level', 'max_hp', 'min_atk', 'max_atk', 'defense', 'exp', 'art']),
        ]);
    }

    public function fight(Request $request, FieldMonster $monster, HuntMonster $hunt): RedirectResponse
    {
        $battle = $hunt->handle($request->user()->character, $monster);

        return to_route('battles.show', $battle);
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\HuntMonster;
use App\Actions\SearchSpot;
use App\Actions\Travel;
use App\Models\Character;
use App\Models\Field;
use App\Models\FieldMonster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
            'searches' => $this->searches($request->user()->character, $field),
        ]);
    }

    /**
     * Go to a hunting ground from the world map.
     */
    public function travel(Request $request, Field $field, Travel $travel): RedirectResponse
    {
        $travel->handle($request->user()->character, $field);

        return to_route('fields.show', $field);
    }

    public function search(Request $request, Field $field, string $spot, SearchSpot $search): RedirectResponse
    {
        $result = $search->handle($request->user()->character, $field, $spot);
        Inertia::flash('toast', ['type' => 'success', 'message' => $result['message']]);

        return $result['battle']
            ? to_route('battles.show', $result['battle'])
            : to_route('fields.show', $field);
    }

    public function fight(Request $request, FieldMonster $monster, HuntMonster $hunt): RedirectResponse
    {
        $battle = $hunt->handle($request->user()->character, $monster);

        return to_route('battles.show', $battle);
    }

    /**
     * The area's search spots with when each is ready again and whether the ninja holds the key.
     *
     * @return list<array<string, mixed>>
     */
    private function searches(Character $character, Field $field): array
    {
        $last = DB::table('character_searches')
            ->where(['character_id' => $character->id, 'field_scene' => $field->scene])
            ->pluck('searched_at', 'spot');
        $keys = $character->inventory()->with('item')->get()->pluck('quantity', 'item.code');

        return collect($field->searches ?? [])->map(fn (array $search) => [
            'spot' => $search['spot'],
            'art' => $search['art'],
            'key' => $search['key'] ? ['name' => "{$field->name} Key", 'owned' => (int) ($keys[$search['key']] ?? 0)] : null,
            'readyAt' => isset($last[$search['spot']])
                ? Carbon::parse($last[$search['spot']])->addSeconds($search['cooldown'])->toIso8601String()
                : null,
        ])->values()->all();
    }
}

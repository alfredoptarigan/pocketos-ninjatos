<?php

namespace App\Http\Controllers;

use App\Actions\LearnSkill;
use App\Game\Skill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SkillController extends Controller
{
    /**
     * Every jutsu, grouped by school on the page, with what the ninja can learn.
     */
    public function index(Request $request): Response
    {
        $character = $request->user()->character;

        $skills = collect(config('game.skills'))->map(fn (array $data, string|int $id) => [
            'id' => (string) $id,
            'name' => $data['name'],
            'school' => $data['school'],
            'kind' => $data['kind'],
            'description' => $data['description'],
            'level' => $data['level'],
            'requires' => $data['requires'],
            'price' => Skill::find((string) $id)->price(),
            'icon' => "/game-assets/skills/$id.png",
            'learned' => in_array((string) $id, $character->skills, true),
        ])->values();

        return Inertia::render('skills', ['skills' => $skills]);
    }

    public function learn(Request $request, string $skill, LearnSkill $learn): RedirectResponse
    {
        abort_unless(config()->has("game.skills.$skill"), 404);
        $jutsu = Skill::find($skill);

        $learn->handle($request->user()->character, $jutsu);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Learned {$jutsu->name}!"]);

        return to_route('skills.index');
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\BuySkillSlot;
use App\Actions\EquipSkill;
use App\Actions\LearnSkill;
use App\Actions\ResetSkills;
use App\Game\Skill;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SkillController extends Controller
{
    /**
     * The skill panel: passives, the 40 jutsu in school columns and tier
     * rows, skill points and the equipped pages.
     */
    public function index(Request $request): Response
    {
        $character = $request->user()->character;
        $passive = $character->passiveLevel();

        return Inertia::render('skills', [
            'passives' => collect(config('skills.schools'))->map(fn (array $school) => [
                'id' => $school['passive'],
                'name' => $school['name'],
                'level' => $passive,
                'icon' => "/game-assets/skills/{$school['passive']}.png",
            ])->values(),
            'skills' => collect(config('skills.skills'))
                ->sortBy(fn (array $data) => [$data['tier'], array_search($data['school'], array_keys(config('skills.schools')), true)])
                ->map(fn (array $data, string|int $id) => $this->summary((string) $id, $data, $character->skills[$id] ?? 0, $passive))
                ->values(),
            'points' => $character->skillPoints(),
            'pages' => $character->skill_pages,
            'page' => $character->skill_page,
            'openSlots' => $character->openSkillSlots(),
            'slotPrices' => config('skills.slots.prices'),
            'slotsBought' => $character->skill_slots_bought,
            'rules' => [
                'maxLevel' => config('skills.max_level'),
                'resetCoupons' => config('skills.reset_coupons'),
                'passive' => config('skills.passive'),
                'upgrade' => config('skills.upgrade'),
            ],
        ]);
    }

    public function learn(Request $request, string $skill, LearnSkill $learn): RedirectResponse
    {
        abort_unless(config()->has("skills.skills.$skill"), 404);

        $level = $learn->handle($request->user()->character, $skill);
        $name = config("skills.skills.$skill.name");

        Inertia::flash('toast', ['type' => 'success', 'message' => $level === 1 ? "Learned {$name}!" : "{$name} is now +".($level - 1).'.']);

        return to_route('skills.index');
    }

    public function reset(Request $request, ResetSkills $reset): RedirectResponse
    {
        $reset->handle($request->user()->character);

        return to_route('skills.index');
    }

    public function equip(Request $request, EquipSkill $equip): RedirectResponse
    {
        $data = $request->validate([
            'skill' => ['required', 'string', function (string $attribute, string $value, Closure $fail) {
                if (! config()->has("skills.skills.$value")) {
                    $fail('Unknown jutsu.');
                }
            }],
            'slot' => ['required', 'integer', 'min:0', 'max:'.(config('skills.slots.total') - 1)],
        ]);

        $equip->handle($request->user()->character, $data['skill'], (int) $data['slot']);

        return to_route('skills.index');
    }

    public function unequip(Request $request, EquipSkill $equip): RedirectResponse
    {
        $data = $request->validate(['slot' => ['required', 'integer', 'min:0', 'max:'.(config('skills.slots.total') - 1)]]);

        $equip->handle($request->user()->character, null, (int) $data['slot']);

        return to_route('skills.index');
    }

    public function page(Request $request): RedirectResponse
    {
        $data = $request->validate(['page' => ['required', 'integer', 'min:0', 'max:'.(config('skills.slots.pages') - 1)]]);

        $request->user()->character->forceFill(['skill_page' => (int) $data['page']])->save();

        return to_route('skills.index');
    }

    public function slots(Request $request, BuySkillSlot $buy): RedirectResponse
    {
        $buy->handle($request->user()->character);

        return to_route('skills.index');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function summary(string $id, array $data, int $level, int $passive): array
    {
        $current = Skill::fromArray($id, $data, max(1, $level), $passive);

        return [
            'id' => $id,
            'name' => $data['name'],
            'school' => $data['school'],
            'tier' => $data['tier'],
            'kind' => $data['kind'],
            'requires' => $data['requires'],
            'description' => $data['description'],
            'chakra' => $data['chakra'],
            'chance' => $current->chance,
            'power' => $current->power,
            'level' => $level,
            'icon' => "/game-assets/skills/$id.png",
        ];
    }
}

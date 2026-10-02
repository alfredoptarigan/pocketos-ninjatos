<?php

namespace Tests\Feature;

use App\Models\Battle;
use App\Models\Character;
use App\Models\TowerFloor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SkillTest extends TestCase
{
    use RefreshDatabase;

    public function test_players_need_a_character_first()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('skills.index'))->assertRedirect(route('character.create'));
    }

    public function test_skills_page_lists_every_jutsu_with_its_state()
    {
        $character = Character::factory()->create(['skills' => ['1808']]);
        $this->actingAs($character->user);

        $this->get(route('skills.index'))->assertInertia(fn (Assert $page) => $page
            ->component('skills')
            ->has('skills', count(config('game.skills')))
            ->where('skills.0.id', '1808')
            ->where('skills.0.learned', true)
            ->where('skills.0.icon', '/game-assets/skills/1808.png'));
    }

    public function test_players_can_learn_a_jutsu()
    {
        $character = Character::factory()->create(['gold' => 1000]);
        $this->actingAs($character->user);

        $this->post(route('skills.learn', '1808'))->assertRedirect(route('skills.index'))->assertSessionHasNoErrors();

        $character->refresh();
        $this->assertSame(['1808'], $character->skills);
        $this->assertSame(1000 - 100, $character->gold); // 50 + level 1 * 50
    }

    public function test_learning_needs_the_level_the_previous_jutsu_and_gold()
    {
        $character = Character::factory()->create(['gold' => 1000]);
        $this->actingAs($character->user);

        // Chidori: level 8 and Falling Thunder first.
        $this->post(route('skills.learn', '1825'))->assertSessionHasErrors('skill');

        $character->forceFill(['level' => 8])->save();
        $this->post(route('skills.learn', '1825'))->assertSessionHasErrors('skill');

        $character->forceFill(['skills' => ['1802'], 'gold' => 10])->save();
        $this->post(route('skills.learn', '1825'))->assertSessionHasErrors('skill');

        $this->assertSame(['1802'], $character->fresh()->skills);
    }

    public function test_a_jutsu_cannot_be_learned_twice_or_if_unknown()
    {
        $character = Character::factory()->create(['skills' => ['1808'], 'gold' => 1000]);
        $this->actingAs($character->user);

        $this->post(route('skills.learn', '1808'))->assertSessionHasErrors('skill');
        $this->post(route('skills.learn', '9999'))->assertNotFound();
        $this->assertSame(1000, $character->fresh()->gold);
    }

    public function test_learned_jutsu_fight_in_the_tower_and_are_logged()
    {
        $character = Character::factory()->create(['skills' => ['1808', '1829']]);
        TowerFloor::factory()->create(['floor' => 1, 'max_hp' => 1, 'dodge' => 0, 'priority' => 0]);
        $this->actingAs($character->user);

        $this->post(route('tower.fight', 1));

        $player = Battle::sole()->log['fighters'][0];
        $this->assertSame(['1808', '1829'], $player['skills']);
        $this->assertSame(56, $player['maxMp']);
    }
}

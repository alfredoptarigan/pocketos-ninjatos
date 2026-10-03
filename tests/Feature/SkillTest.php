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

    private function ninja(array $overrides = []): Character
    {
        return Character::factory()->create(['level' => 10, 'coupons' => 0, ...$overrides]);
    }

    public function test_players_need_a_character_first()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('skills.index'))->assertRedirect(route('character.create'));
    }

    public function test_the_skills_page_lists_passives_actives_and_the_loadout()
    {
        $character = $this->ninja(['skills' => ['1808' => 2], 'skill_pages' => [['1808'], [], []]]);
        $this->actingAs($character->user);

        $this->get(route('skills.index'))->assertInertia(fn (Assert $page) => $page
            ->component('skills')
            ->has('passives', 10)
            ->where('passives.0.id', '2801')
            ->where('passives.0.level', 1)
            ->has('skills', 40)
            ->where('skills.0.id', '1808')
            ->where('skills.0.level', 2)
            ->where('skills.0.icon', '/game-assets/skills/1808.png')
            ->where('points', 9 - 2)
            ->where('pages.0.0', '1808')
            ->where('page', 0)
            ->where('openSlots', 4)); // level 10
    }

    public function test_learning_costs_a_point_and_needs_the_previous_jutsu()
    {
        $character = $this->ninja(['level' => 3]);
        $this->actingAs($character->user);

        $this->post(route('skills.learn', '1825'))->assertSessionHasErrors('skill'); // Chidori needs Static Field
        $this->post(route('skills.learn', '1802'))->assertSessionHasNoErrors();
        $this->post(route('skills.learn', '1839'))->assertSessionHasNoErrors();
        $this->post(route('skills.learn', '1808'))->assertSessionHasErrors('skill'); // level 3: 2 points only

        $this->assertSame(['1802' => 1, '1839' => 1], $character->refresh()->skills);
        $this->post(route('skills.learn', '9999'))->assertNotFound();
    }

    public function test_learning_a_known_jutsu_upgrades_it_up_to_the_max_level()
    {
        $character = $this->ninja(['level' => 100, 'skills' => ['1808' => 12]]);
        $this->actingAs($character->user);

        $this->post(route('skills.learn', '1808'))->assertSessionHasNoErrors();
        $this->post(route('skills.learn', '1808'))->assertSessionHasErrors('skill');

        $this->assertSame(['1808' => 13], $character->refresh()->skills);
    }

    public function test_resetting_refunds_every_point_for_gift_coupons()
    {
        $character = $this->ninja(['coupons' => 10, 'skills' => ['1808' => 3], 'skill_pages' => [['1808'], [], []]]);
        $this->actingAs($character->user);

        $this->post(route('skills.reset'))->assertSessionHasNoErrors();

        $character->refresh();
        $this->assertSame([], $character->skills);
        $this->assertSame([[], [], []], $character->skill_pages);
        $this->assertSame(10 - config('skills.reset_coupons'), $character->coupons);

        $this->post(route('skills.reset'))->assertSessionHasErrors('coupons');
    }

    public function test_equipping_fills_open_slots_of_the_current_page()
    {
        $character = $this->ninja(['level' => 1, 'skills' => ['1808' => 1, '1802' => 1, '1826' => 1, '1807' => 1, '3813' => 1, '3815' => 1]]);
        $this->actingAs($character->user);

        $this->post(route('skills.equip'), ['skill' => '1808', 'slot' => 0])->assertSessionHasNoErrors();
        $this->post(route('skills.equip'), ['skill' => '1802', 'slot' => 1])->assertSessionHasNoErrors();
        $this->post(route('skills.equip'), ['skill' => '1808', 'slot' => 2])->assertSessionHasNoErrors(); // moves
        $this->post(route('skills.equip'), ['skill' => '1826', 'slot' => 3])->assertSessionHasErrors('slot'); // locked
        $this->post(route('skills.equip'), ['skill' => '1829', 'slot' => 0])->assertSessionHasErrors('skill'); // not learned
        $this->post(route('skills.equip'), ['skill' => '3813', 'slot' => 0])->assertSessionHasNoErrors();
        $this->post(route('skills.equip'), ['skill' => '3815', 'slot' => 1])->assertSessionHasErrors('skill'); // excludes Mist-hide

        $this->assertSame(['3813', '1802', '1808'], $character->refresh()->skill_pages[0]);

        $this->post(route('skills.unequip'), ['slot' => 1])->assertSessionHasNoErrors();
        $this->post(route('skills.page'), ['page' => 1])->assertSessionHasNoErrors();
        $this->post(route('skills.equip'), ['skill' => '1807', 'slot' => 0])->assertSessionHasNoErrors();

        $character->refresh();
        $this->assertSame([['3813', null, '1808'], ['1807'], []], $character->skill_pages);
        $this->assertSame(1, $character->skill_page);
    }

    public function test_extra_slots_open_with_level_and_can_be_bought()
    {
        $this->assertSame(3, $this->ninja(['level' => 9])->openSkillSlots());
        $this->assertSame(6, $this->ninja(['level' => 30])->openSkillSlots());

        $character = $this->ninja(['level' => 30, 'coupons' => 25]);
        $this->actingAs($character->user);

        $this->post(route('skills.slots'))->assertSessionHasNoErrors();
        $this->post(route('skills.slots'))->assertSessionHasErrors('coupons');

        $character->refresh();
        $this->assertSame(7, $character->openSkillSlots());
        $this->assertSame(5, $character->coupons);
    }

    public function test_only_the_equipped_page_fights_with_levels_and_passives()
    {
        $character = $this->ninja(['level' => 11, 'skills' => ['1808' => 3, '1829' => 1], 'skill_pages' => [['1808'], ['1829'], []]]);
        TowerFloor::factory()->create(['floor' => 1, 'max_hp' => 1, 'dodge' => 0, 'priority' => 0]);
        $this->actingAs($character->user);

        $fireball = $character->equippedSkills()[0];
        $this->assertCount(1, $character->equippedSkills());
        // Level 3 and Fire Release level 2 (character level 11).
        $this->assertSame(33 + 2 * 1 + 2 * 1, $fireball->chance);
        $this->assertSame((int) round(130 * (100 + 2 * 5 + 2 * 3) / 100), $fireball->power);

        $this->post(route('tower.fight', 1));

        $this->assertSame(['1808'], Battle::sole()->log['fighters'][0]['skills']);
    }
}

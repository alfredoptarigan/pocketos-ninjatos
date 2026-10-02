<?php

namespace Tests\Feature;

use App\Models\Battle;
use App\Models\Character;
use App\Models\Equipment;
use App\Models\Field;
use App\Models\FieldMonster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Lottery;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_players_need_a_character_first()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('world.show'))->assertRedirect(route('character.create'));
    }

    public function test_world_map_lists_the_areas_and_their_monsters()
    {
        $monster = FieldMonster::factory()->create(['name' => 'Sunflower']);
        $this->actingAs(Character::factory()->create()->user);

        $this->get(route('world.show'))->assertInertia(fn (Assert $page) => $page
            ->component('world')
            ->has('fields', 1)
            ->where('fields.0.scene', $monster->field_scene)
            ->where('fields.0.monsters.0.name', 'Sunflower')
            ->has('villages', count(config('game.villages'))));
    }

    public function test_an_area_shows_its_monsters()
    {
        $monster = FieldMonster::factory()->create();
        $this->actingAs(Character::factory()->create()->user);

        $this->get(route('fields.show', $monster->field_scene))->assertInertia(fn (Assert $page) => $page
            ->component('field')
            ->where('field.scene', $monster->field_scene)
            ->where('monsters.0.id', $monster->id));
    }

    public function test_areas_above_the_ninjas_level_are_closed()
    {
        $field = Field::factory()->create(['level' => 11]);
        $this->actingAs(Character::factory()->create()->user);

        $this->get(route('fields.show', $field))->assertRedirect(route('world.show'));
    }

    public function test_beating_a_monster_pays_out_and_keeps_the_wounds()
    {
        $character = Character::factory()->create(['gold' => 0]);
        $monster = FieldMonster::factory()->create(['exp' => 30, 'level' => 4, 'priority' => 0]);
        $this->actingAs($character->user);

        Lottery::alwaysLose();
        $response = $this->post(route('fields.fight', $monster));
        Lottery::determineResultNormally();

        $battle = Battle::sole();
        $response->assertRedirect(route('battles.show', $battle));
        $this->assertTrue($battle->won);
        $this->assertNull($battle->floor);
        $this->assertSame($monster->id, $battle->field_monster_id);
        $character->refresh();
        $this->assertSame(30 * config('game.fields.exp_multiplier'), $character->exp);
        $this->assertSame(4 * config('game.fields.gold_per_level'), $character->gold);
        // Health is recorded, not reset like in the tower.
        $this->assertNotNull($character->vitals_at);
        $this->assertNull($battle->rewards['drop']);
        $this->assertSame($monster->field->background, $battle->log['fighters'][1]['art']['background']);
    }

    public function test_lucky_wins_drop_gear()
    {
        $character = Character::factory()->create();
        Equipment::factory()->create();
        $monster = FieldMonster::factory()->create();
        $this->actingAs($character->user);

        Lottery::alwaysWin(fn () => $this->post(route('fields.fight', $monster)));

        $this->assertSame(1, $character->gear()->count());
    }

    public function test_a_knocked_out_ninja_cannot_hunt()
    {
        $character = Character::factory()->create();
        $character->setVitals(0, 0);
        $character->save();
        $monster = FieldMonster::factory()->create();
        $this->actingAs($character->user);

        $this->post(route('fields.fight', $monster))->assertSessionHasErrors('monster');

        $this->assertSame(0, Battle::count());
    }

    public function test_the_battle_page_links_back_to_the_area()
    {
        $character = Character::factory()->create();
        $monster = FieldMonster::factory()->create();
        $this->actingAs($character->user);
        $this->post(route('fields.fight', $monster));

        $this->get(route('battles.show', Battle::sole()))->assertInertia(fn (Assert $page) => $page
            ->where('battle.field.scene', $monster->field_scene)
            ->where('battle.field.monster', $monster->id));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Dungeon;
use App\Models\DungeonRun;
use App\Models\Field;
use App\Models\FieldMonster;
use App\Models\TowerFloor;
use Database\Factories\DungeonFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The ninja is somewhere: the village, a hunting ground or a dungeon. Typing
 * another place's URL does not move them; only the in-game travel actions do.
 */
class LocationTest extends TestCase
{
    use RefreshDatabase;

    private function ninja(array $overrides = []): Character
    {
        $character = Character::factory()->create(['level' => 30, ...$overrides]);
        $this->actingAs($character->user);

        return $character;
    }

    private function dungeon(): Dungeon
    {
        return Dungeon::factory()->create(['min_level' => 1, 'stages' => [DungeonFactory::stage(2)]]);
    }

    public function test_typing_a_field_url_from_the_village_goes_back_to_the_village()
    {
        $this->ninja();
        $field = Field::factory()->create();

        $this->get(route('fields.show', $field))->assertRedirect(route('village'));
    }

    public function test_travelling_from_the_world_map_opens_the_field()
    {
        $character = $this->ninja();
        $field = Field::factory()->create();

        $this->post(route('fields.travel', $field))->assertRedirect(route('fields.show', $field));

        $this->assertSame("field:{$field->scene}", $character->refresh()->location);
        $this->actingAs($character->user->fresh());
        $this->get(route('fields.show', $field))->assertOk();
    }

    public function test_travel_to_a_field_needs_its_level()
    {
        $character = $this->ninja(['level' => 5]);
        $field = Field::factory()->create(['level' => 11]);

        $this->post(route('fields.travel', $field))->assertSessionHasErrors('location');

        $this->assertNull($character->refresh()->location);
    }

    public function test_a_ninja_in_a_field_can_travel_to_another_field_but_not_type_its_url()
    {
        $here = Field::factory()->create();
        $there = Field::factory()->create();
        $this->ninja(['location' => "field:{$here->scene}"]);

        $this->get(route('fields.show', $there))->assertRedirect(route('fields.show', $here));
        $this->get(route('world.show'))->assertOk();
        $this->post(route('fields.travel', $there))->assertRedirect(route('fields.show', $there));
    }

    public function test_monsters_of_another_field_cannot_be_fought_or_searched()
    {
        $here = Field::factory()->create();
        $monster = FieldMonster::factory()->create();
        $character = $this->ninja(['location' => "field:{$here->scene}"]);

        $this->post(route('fields.fight', $monster))->assertRedirect(route('fields.show', $here));
        $this->post(route('fields.search', [$monster->field_scene, 'search']))->assertRedirect(route('fields.show', $here));

        $this->assertSame(0, $character->battles()->count());
    }

    public function test_village_buildings_are_closed_while_away()
    {
        $field = Field::factory()->create();
        $floor = TowerFloor::factory()->create(['floor' => 1]);
        $character = $this->ninja(['location' => "field:{$field->scene}"]);

        foreach (['village', 'tower.show', 'pharmacy.show', 'equipment-shop.show', 'wish-pot.show', 'dungeons.index'] as $route) {
            $this->get(route($route))->assertRedirect(route('fields.show', $field));
        }
        $this->post(route('tower.fight', $floor))->assertRedirect(route('fields.show', $field));
        $this->assertSame(0, $character->battles()->count());

        // Menus open anywhere.
        $this->get(route('bag'))->assertOk();
        $this->get(route('skills.index'))->assertOk();
    }

    public function test_returning_to_the_village_leaves_the_field()
    {
        $field = Field::factory()->create();
        $character = $this->ninja(['location' => "field:{$field->scene}"]);

        $this->post(route('village.return'))->assertRedirect(route('village'));

        $this->assertNull($character->refresh()->location);
    }

    public function test_travelling_to_a_village_from_the_world_map_leaves_the_field()
    {
        $field = Field::factory()->create();
        $character = $this->ninja(['location' => "field:{$field->scene}"]);

        $this->post(route('village.travel'), ['village' => '121'])->assertRedirect(route('village'));

        $this->assertNull($character->refresh()->location);
    }

    public function test_typing_a_dungeon_url_goes_back_to_where_the_ninja_is()
    {
        $this->ninja();
        $dungeon = $this->dungeon();

        $this->get(route('dungeons.show', $dungeon))->assertRedirect(route('village'));
        $this->post(route('dungeons.enter', $dungeon))->assertRedirect(route('village'));
        $this->assertSame(0, DungeonRun::count());
    }

    public function test_opening_a_dungeon_from_the_list_goes_inside()
    {
        $character = $this->ninja();
        $dungeon = $this->dungeon();

        $this->post(route('dungeons.open', $dungeon))->assertRedirect(route('dungeons.show', $dungeon));

        $this->assertSame("dungeon:{$dungeon->id}", $character->refresh()->location);
        $this->actingAs($character->user->fresh());
        $this->get(route('dungeons.show', $dungeon))->assertOk();
    }

    public function test_opening_a_dungeon_needs_its_level_and_the_village()
    {
        $dungeon = Dungeon::factory()->create(['min_level' => 40, 'stages' => [DungeonFactory::stage(2)]]);
        $this->ninja();

        $this->post(route('dungeons.open', $dungeon))->assertSessionHasErrors('location');

        $field = Field::factory()->create();
        $this->ninja(['location' => "field:{$field->scene}"]);
        $this->post(route('dungeons.open', $this->dungeon()))->assertRedirect(route('fields.show', $field));
    }

    public function test_inside_a_dungeon_the_world_and_other_dungeons_are_closed()
    {
        $dungeon = $this->dungeon();
        $other = $this->dungeon();
        $field = Field::factory()->create();
        $character = $this->ninja(['location' => "dungeon:{$dungeon->id}"]);
        $run = DungeonRun::factory()->for($character)->for($other)->create();

        $this->get(route('world.show'))->assertRedirect(route('dungeons.show', $dungeon));
        $this->get(route('dungeons.show', $other))->assertRedirect(route('dungeons.show', $dungeon));
        $this->post(route('dungeon-runs.fight', $run))->assertRedirect(route('dungeons.show', $dungeon));
        $this->post(route('fields.travel', $field))->assertRedirect(route('dungeons.show', $dungeon));
    }

    public function test_leaving_the_dungeon_goes_back_to_the_list()
    {
        $dungeon = $this->dungeon();
        $character = $this->ninja(['location' => "dungeon:{$dungeon->id}"]);

        $this->post(route('dungeons.exit'))->assertRedirect(route('dungeons.index'));

        $this->assertNull($character->refresh()->location);
    }

    public function test_an_unfinished_run_must_be_left_before_going_out()
    {
        $dungeon = $this->dungeon();
        $character = $this->ninja(['location' => "dungeon:{$dungeon->id}"]);
        $run = DungeonRun::factory()->for($character)->for($dungeon)->create();

        $this->post(route('dungeons.exit'))->assertSessionHasErrors('location');
        $this->assertSame("dungeon:{$dungeon->id}", $character->refresh()->location);

        $this->post(route('dungeon-runs.leave', $run))->assertRedirect(route('dungeons.index'));
        $this->assertNull($character->refresh()->location);
    }
}

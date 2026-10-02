<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\CharacterEquipment;
use App\Models\Equipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GearTest extends TestCase
{
    use RefreshDatabase;

    private function give(Character $character, Equipment $equipment, ?string $wornIn = null): CharacterEquipment
    {
        return $character->gear()->forceCreate(['equipment_id' => $equipment->id, 'equipped_slot' => $wornIn]);
    }

    public function test_players_need_a_character_first()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('character.show'))->assertRedirect(route('character.create'));
    }

    public function test_character_panel_shows_worn_gear_bag_and_stats()
    {
        $character = Character::factory()->create();
        $this->give($character, Equipment::factory()->create(['name' => 'Tickle Gloves']), 'weapon');
        $this->give($character, Equipment::factory()->slot('hat', 3, ['defense' => 24])->create());
        $this->actingAs($character->user);

        $this->get(route('character.show'))->assertInertia(fn (Assert $page) => $page
            ->component('character/show')
            ->where('worn.weapon.name', 'Tickle Gloves')
            ->has('bag', 1)
            ->where('bag.0.slot', 'hat')
            ->where('stats.minAttack', 20 + 14)
            ->where('stats.maxAttack', 25 + 16));
    }

    public function test_equipping_swaps_out_the_piece_in_that_slot()
    {
        $character = Character::factory()->create();
        $old = $this->give($character, Equipment::factory()->create(), 'weapon');
        $new = $this->give($character, Equipment::factory()->create(['min_attack' => 50, 'max_attack' => 60]));
        $this->actingAs($character->user);

        $this->post(route('character.gear.equip', $new))->assertRedirect(route('character.show'));

        $this->assertNull($old->refresh()->equipped_slot);
        $this->assertSame('weapon', $new->refresh()->equipped_slot);
        $this->assertSame(20 + 50, $character->refresh()->stats()->minAttack);
    }

    public function test_gear_above_the_ninjas_level_cannot_be_worn()
    {
        $character = Character::factory()->create(['level' => 5]);
        $piece = $this->give($character, Equipment::factory()->slot('hat', 13)->create());
        $this->actingAs($character->user);

        $this->post(route('character.gear.equip', $piece))->assertSessionHasErrors('gear');

        $this->assertNull($piece->refresh()->equipped_slot);
    }

    public function test_unequipping_puts_the_piece_back_in_the_bag()
    {
        $character = Character::factory()->create();
        $piece = $this->give($character, Equipment::factory()->create(), 'weapon');
        $this->actingAs($character->user);

        $this->post(route('character.gear.unequip', $piece))->assertRedirect(route('character.show'));

        $this->assertNull($piece->refresh()->equipped_slot);
    }

    public function test_players_cannot_touch_other_players_gear()
    {
        $piece = $this->give(Character::factory()->create(), Equipment::factory()->create(), 'weapon');
        $this->actingAs(Character::factory()->create()->user);

        $this->post(route('character.gear.equip', $piece))->assertNotFound();
        $this->post(route('character.gear.unequip', $piece))->assertNotFound();
        $this->assertSame('weapon', $piece->refresh()->equipped_slot);
    }
}

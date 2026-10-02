<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Equipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EquipmentShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_players_need_a_character_first()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('equipment-shop.show'))->assertRedirect(route('character.create'));
    }

    public function test_the_shop_stocks_gear_up_to_a_few_levels_ahead()
    {
        $character = Character::factory()->create(['level' => 5]);
        Equipment::factory()->slot('hat', 3)->create(['price' => 40]);
        Equipment::factory()->slot('hat', 13)->create();
        Equipment::factory()->slot('hat', 23)->create();
        $this->actingAs($character->user);

        $this->get(route('equipment-shop.show'))->assertInertia(fn (Assert $page) => $page
            ->component('buildings/equipment-shop')
            ->has('stock', 2)
            ->where('stock.0.buy_price', 40 * (10 + 3))
            ->where('stock.0.sell_price', intdiv(40 * 13 * 25, 100)));
    }

    public function test_buying_puts_the_piece_in_the_bag()
    {
        $character = Character::factory()->create(['gold' => 1000]);
        $equipment = Equipment::factory()->create(['price' => 44, 'level' => 1]);
        $this->actingAs($character->user);

        $this->post(route('equipment-shop.buy', $equipment))->assertRedirect(route('equipment-shop.show'));

        $this->assertSame(1000 - 44 * 11, $character->refresh()->gold);
        $this->assertSame($equipment->id, $character->gear()->sole()->equipment_id);
    }

    public function test_gear_costs_gold()
    {
        $character = Character::factory()->create(['gold' => 10]);
        $equipment = Equipment::factory()->create();
        $this->actingAs($character->user);

        $this->post(route('equipment-shop.buy', $equipment))->assertSessionHasErrors('gear');

        $this->assertSame(0, $character->gear()->count());
    }

    public function test_selling_pays_a_quarter_and_needs_the_piece_off()
    {
        $character = Character::factory()->create(['gold' => 0]);
        $equipment = Equipment::factory()->create(['price' => 44, 'level' => 1]);
        $worn = $character->gear()->forceCreate(['equipment_id' => $equipment->id, 'equipped_slot' => 'weapon']);
        $spare = $character->gear()->forceCreate(['equipment_id' => $equipment->id]);
        $this->actingAs($character->user);

        $this->post(route('equipment-shop.sell', $worn))->assertSessionHasErrors('gear');
        $this->post(route('equipment-shop.sell', $spare))->assertRedirect(route('equipment-shop.show'));

        $this->assertSame(intdiv(44 * 11 * 25, 100), $character->refresh()->gold);
        $this->assertSame([$worn->id], $character->gear()->pluck('id')->all());
    }

    public function test_players_cannot_sell_other_players_gear()
    {
        $piece = Character::factory()->create()->gear()->forceCreate(['equipment_id' => Equipment::factory()->create()->id]);
        $this->actingAs(Character::factory()->create()->user);

        $this->post(route('equipment-shop.sell', $piece))->assertNotFound();
    }
}

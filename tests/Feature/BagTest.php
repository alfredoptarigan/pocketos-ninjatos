<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BagTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('bag'))->assertRedirect(route('login'));
    }

    public function test_players_need_a_character_first()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('bag'))->assertRedirect(route('character.create'));
    }

    public function test_bag_lists_only_the_players_own_items()
    {
        $character = Character::factory()->create();
        $potion = Item::factory()->create(['name' => 'Healing Powder']);
        $character->inventory()->create(['item_id' => $potion->id, 'quantity' => 7]);
        Character::factory()->create()->inventory()->create(['item_id' => Item::factory()->create()->id, 'quantity' => 1]);
        $this->actingAs($character->user);

        $this->get(route('bag'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('inventory')
                ->has('items', 1)
                ->where('items.0.name', 'Healing Powder')
                ->where('items.0.quantity', 7));
    }

    public function test_character_shared_prop_includes_the_current_village()
    {
        $character = Character::factory()->create(['village' => '141']);
        $this->actingAs($character->user);

        $this->get(route('bag'))->assertInertia(fn (Assert $page) => $page->where('character.village', '141'));
    }

    public function test_using_a_potion_restores_health_and_uses_it_up()
    {
        $character = Character::factory()->create(['hp' => 10, 'vitals_at' => now()]);
        $potion = Item::factory()->create(['restore_hp' => 150, 'restore_chakra' => 0]);
        $character->inventory()->create(['item_id' => $potion->id, 'quantity' => 1]);
        $this->actingAs($character->user);

        $this->post(route('bag.use'), ['item_id' => $potion->id])->assertRedirect(route('bag'));

        $this->assertSame(110, $character->fresh()->hp); // capped at level 1 max health
        $this->assertDatabaseCount('inventory_items', 0);
    }

    public function test_items_must_be_in_the_bag_and_usable()
    {
        $character = Character::factory()->create();
        $notOwned = Item::factory()->create();
        $energy = Item::factory()->create(['restore_hp' => 0, 'restore_chakra' => 0, 'restore_energy' => 20]);
        $character->inventory()->create(['item_id' => $energy->id, 'quantity' => 1]);
        $this->actingAs($character->user);

        $this->post(route('bag.use'), ['item_id' => $notOwned->id])->assertSessionHasErrors('item_id');
        $this->post(route('bag.use'), ['item_id' => $energy->id])->assertSessionHasErrors('item_id');
        $this->assertDatabaseHas('inventory_items', ['item_id' => $energy->id, 'quantity' => 1]);
    }
}

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
                ->component('bag')
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
}

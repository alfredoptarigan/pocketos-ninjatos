<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PharmacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('pharmacy.show'))->assertRedirect(route('login'));
    }

    public function test_players_need_a_character_first()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('pharmacy.show'))->assertRedirect(route('character.create'));
    }

    public function test_shop_lists_pharmacy_items_with_owned_quantities()
    {
        $character = Character::factory()->create(['gold' => 500]);
        $potion = Item::factory()->create(['name' => 'Bubuk Penyembuh']);
        Item::factory()->create(['category' => 'equipment']);
        $character->inventory()->create(['item_id' => $potion->id, 'quantity' => 3]);
        $this->actingAs($character->user);

        $this->get(route('pharmacy.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('buildings/pharmacy')
                ->has('items', 1)
                ->where('items.0.name', 'Bubuk Penyembuh')
                ->where('owned.'.$potion->id, 3)
                ->where('character.gold', 500));
    }

    public function test_players_can_buy_items()
    {
        $character = Character::factory()->create(['gold' => 100]);
        $potion = Item::factory()->create(['price' => 16]);
        $this->actingAs($character->user);

        $this->post(route('pharmacy.buy'), ['item_id' => $potion->id, 'quantity' => 5])
            ->assertRedirect(route('pharmacy.show'))
            ->assertSessionHasNoErrors();

        $this->assertSame(20, $character->fresh()->gold);
        $this->assertDatabaseHas('inventory_items', [
            'character_id' => $character->id,
            'item_id' => $potion->id,
            'quantity' => 5,
        ]);
    }

    public function test_buying_again_adds_to_the_same_stack()
    {
        $character = Character::factory()->create(['gold' => 1000]);
        $potion = Item::factory()->create(['price' => 10]);
        $this->actingAs($character->user);

        $this->post(route('pharmacy.buy'), ['item_id' => $potion->id, 'quantity' => 2]);
        $this->post(route('pharmacy.buy'), ['item_id' => $potion->id, 'quantity' => 3]);

        $this->assertDatabaseCount('inventory_items', 1);
        $this->assertSame(5, $character->inventory()->first()->quantity);
    }

    public function test_players_cannot_spend_more_gold_than_they_have()
    {
        $character = Character::factory()->create(['gold' => 30]);
        $potion = Item::factory()->create(['price' => 16]);
        $this->actingAs($character->user);

        $this->post(route('pharmacy.buy'), ['item_id' => $potion->id, 'quantity' => 2])
            ->assertSessionHasErrors('quantity');

        $this->assertSame(30, $character->fresh()->gold);
        $this->assertDatabaseCount('inventory_items', 0);
    }

    public function test_stacks_cannot_exceed_the_item_limit()
    {
        $character = Character::factory()->create(['gold' => 10000]);
        $potion = Item::factory()->create(['price' => 1, 'max_stack' => 10]);
        $character->inventory()->create(['item_id' => $potion->id, 'quantity' => 8]);
        $this->actingAs($character->user);

        $this->post(route('pharmacy.buy'), ['item_id' => $potion->id, 'quantity' => 3])
            ->assertSessionHasErrors('quantity');

        $this->assertSame(10000, $character->fresh()->gold);
        $this->assertSame(8, $character->inventory()->first()->quantity);
    }

    public function test_only_pharmacy_items_can_be_bought_here()
    {
        $character = Character::factory()->create(['gold' => 10000]);
        $sword = Item::factory()->create(['category' => 'equipment']);
        $this->actingAs($character->user);

        $this->post(route('pharmacy.buy'), ['item_id' => $sword->id, 'quantity' => 1])
            ->assertSessionHasErrors('item_id');
    }

    public function test_quantity_must_be_a_sensible_number()
    {
        $character = Character::factory()->create(['gold' => 10000]);
        $potion = Item::factory()->create();
        $this->actingAs($character->user);

        foreach ([0, -1, 1000, 'banyak'] as $quantity) {
            $this->post(route('pharmacy.buy'), ['item_id' => $potion->id, 'quantity' => $quantity])
                ->assertSessionHasErrors('quantity');
        }
    }
}

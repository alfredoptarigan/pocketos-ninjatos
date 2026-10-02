<?php

namespace Tests\Feature;

use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VillageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('village'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_village()
    {
        $this->actingAs(Character::factory()->create()->user);

        $response = $this->get(route('village'));
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('village'));
    }

    public function test_unverified_users_can_visit_the_village()
    {
        $character = Character::factory()->create();
        $character->user->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($character->user);

        $response = $this->get(route('village'));
        $response->assertOk();
    }

    public function test_page_shows_the_current_village_and_travel_options()
    {
        $this->actingAs(Character::factory()->create(['village' => '121'])->user);

        $this->get(route('village'))->assertInertia(fn (Assert $page) => $page
            ->where('village.id', '121')
            ->where('village.name', config('game.villages.121'))
            ->has('villages', count(config('game.villages'))));
    }

    public function test_new_characters_start_in_the_leaf_village()
    {
        $user = Character::factory()->create()->user;
        $user->character->delete();
        $this->actingAs($user);

        $this->post(route('character.store'), ['name' => 'Rookie', 'avatar' => '0_12']);

        $this->assertSame(config('game.home_village'), $user->character()->first()->village);
    }

    public function test_players_can_travel_to_another_village()
    {
        $character = Character::factory()->create(['village' => '111']);
        $this->actingAs($character->user);

        $this->post(route('village.travel'), ['village' => '131'])
            ->assertRedirect(route('village'))
            ->assertSessionHasNoErrors();

        $this->assertSame('131', $character->fresh()->village);
    }

    public function test_travel_rejects_unknown_villages()
    {
        $character = Character::factory()->create(['village' => '111']);
        $this->actingAs($character->user);

        foreach (['999', '', 'leaf'] as $village) {
            $this->post(route('village.travel'), ['village' => $village])->assertSessionHasErrors('village');
        }

        $this->assertSame('111', $character->fresh()->village);
    }
}

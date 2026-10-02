<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CharacterTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('character.create'))->assertRedirect(route('login'));
    }

    public function test_players_without_a_character_are_sent_to_create_one()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('village'))->assertRedirect(route('character.create'));
    }

    public function test_create_screen_lists_the_avatars()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('character.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('character/create')
                ->has('avatars', count(config('game.avatars'))));
    }

    public function test_players_can_create_a_character()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post(route('character.store'), [
            'name' => 'Naruto',
            'avatar' => '0_12',
        ]);

        $response->assertRedirect(route('village'));
        $this->assertDatabaseHas('characters', [
            'user_id' => $user->id,
            'name' => 'Naruto',
            'avatar' => '0_12',
            'level' => 1,
            'gold' => config('game.starting_gold'),
        ]);
    }

    public function test_character_name_must_be_unique()
    {
        Character::factory()->create(['name' => 'Naruto']);
        $this->actingAs(User::factory()->create());

        $this->post(route('character.store'), ['name' => 'Naruto', 'avatar' => '0_12'])
            ->assertSessionHasErrors('name');
    }

    public function test_character_name_rejects_invalid_values()
    {
        $this->actingAs(User::factory()->create());

        foreach (['', 'ab', str_repeat('a', 17), 'nama<script>'] as $name) {
            $this->post(route('character.store'), ['name' => $name, 'avatar' => '0_12'])
                ->assertSessionHasErrors('name');
        }
    }

    public function test_avatar_must_be_one_of_the_offered_avatars()
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('character.store'), ['name' => 'Naruto', 'avatar' => '9_999'])
            ->assertSessionHasErrors('avatar');
    }

    public function test_players_with_a_character_cannot_create_another()
    {
        $character = Character::factory()->create();
        $this->actingAs($character->user);

        $this->get(route('character.create'))->assertRedirect(route('village'));
        $this->post(route('character.store'), ['name' => 'Kedua', 'avatar' => '0_12'])
            ->assertRedirect(route('village'));

        $this->assertDatabaseCount('characters', 1);
    }

    public function test_character_is_shared_with_every_page()
    {
        $character = Character::factory()->create(['name' => 'Sakura', 'avatar' => '1_26']);
        $this->actingAs($character->user);

        $this->get(route('village'))->assertInertia(fn (Assert $page) => $page
            ->where('character.name', 'Sakura')
            ->where('character.avatar', '1_26')
            ->where('character.level', 1));
    }
}

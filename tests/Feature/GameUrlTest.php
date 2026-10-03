<?php

namespace Tests\Feature;

use App\Models\Battle;
use App\Models\Character;
use App\Models\Dungeon;
use App\Models\Field;
use App\Models\User;
use Database\Factories\DungeonFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Like the original client, the whole game lives at "/": the address bar never
 * shows /battles/209 or /fields/2101, and typing such a URL lands back on "/".
 */
class GameUrlTest extends TestCase
{
    use RefreshDatabase;

    /** What a browser sends when the address bar loads a page (not Inertia's XHR). */
    private const TYPED = ['Sec-Fetch-Mode' => 'navigate'];

    public function test_guests_see_the_welcome_page_at_the_root()
    {
        $this->get('/')->assertInertia(fn (Assert $page) => $page->component('welcome'));
    }

    public function test_players_without_a_ninja_are_sent_to_create_one()
    {
        $this->actingAs(User::factory()->create());

        $this->get('/')->assertRedirect(route('character.create'));
    }

    public function test_the_root_opens_where_the_ninja_is()
    {
        $field = Field::factory()->create();
        $dungeon = Dungeon::factory()->create(['min_level' => 1, 'stages' => [DungeonFactory::stage(2)]]);

        $this->actingAs(Character::factory()->create()->user);
        $this->get('/', self::TYPED)->assertInertia(fn (Assert $page) => $page->component('village')->url('/'));

        $this->actingAs(Character::factory()->create(['location' => "field:{$field->scene}"])->user);
        $this->get('/', self::TYPED)->assertInertia(fn (Assert $page) => $page->component('field')->url('/'));

        $this->actingAs(Character::factory()->create(['location' => "dungeon:{$dungeon->id}"])->user);
        $this->get('/', self::TYPED)->assertInertia(fn (Assert $page) => $page->component('dungeons/show')->url('/'));
    }

    public function test_typed_game_urls_go_back_to_the_root()
    {
        $character = Character::factory()->create();
        $battle = Battle::factory()->for($character)->create();
        $this->actingAs($character->user);

        foreach ([route('battles.show', $battle), route('village'), route('bag'), route('tower.show')] as $url) {
            $this->get($url, self::TYPED)->assertRedirect('/');
        }
        // Before route binding: a missing id must not answer differently (404) and reveal which ids exist.
        $this->get('/battles/999999', self::TYPED)->assertRedirect('/');
    }

    public function test_game_pages_opened_in_game_report_the_root_url()
    {
        $character = Character::factory()->create();
        $battle = Battle::factory()->for($character)->create();
        $this->actingAs($character->user);

        $this->get(route('battles.show', $battle))->assertInertia(fn (Assert $page) => $page->component('battle')->url('/'));
        $this->get(route('bag'))->assertInertia(fn (Assert $page) => $page->url('/'));
    }

    public function test_settings_keep_their_own_url()
    {
        $this->actingAs(Character::factory()->create()->user);

        $this->get(route('profile.edit'))->assertInertia(fn (Assert $page) => $page->url('/settings/profile'));
    }
}

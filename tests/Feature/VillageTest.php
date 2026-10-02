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
}

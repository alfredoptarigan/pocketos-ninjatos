<?php

namespace Tests\Feature;

use App\Game\Leveling;
use App\Models\Character;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SignInTest extends TestCase
{
    use RefreshDatabase;

    private function ninja(array $overrides = []): Character
    {
        return Character::factory()->create(['level' => 10, 'exp' => 0, 'coupons' => 0, ...$overrides]);
    }

    public function test_players_need_a_character_first()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('gifts.show'))->assertRedirect(route('character.create'));
    }

    public function test_gifts_page_shows_the_streak_and_whether_today_is_claimed()
    {
        $character = $this->ninja(['sign_in_streak' => 2, 'signed_in_on' => Carbon::yesterday()]);
        $this->actingAs($character->user);

        $this->get(route('gifts.show'))->assertInertia(fn (Assert $page) => $page
            ->component('gifts')
            ->where('streak', 2)
            ->where('claimedToday', false)
            ->has('rewards', 7));
    }

    public function test_first_sign_in_pays_exp_and_coupons()
    {
        $character = $this->ninja();
        $this->actingAs($character->user);

        $this->post(route('gifts.sign-in'))->assertRedirect(route('gifts.show'));

        $character->refresh();
        $this->assertSame(1, $character->sign_in_streak);
        $this->assertTrue($character->signed_in_on->isToday());
        $this->assertSame(config('game.sign_in.coupons')[0], $character->coupons);
        $this->assertSame(intdiv(Leveling::expToNext(10) * config('game.sign_in.exp_percent'), 100), $character->exp);
    }

    public function test_only_once_a_day()
    {
        $character = $this->ninja(['sign_in_streak' => 1, 'signed_in_on' => Carbon::today()]);
        $this->actingAs($character->user);

        $this->post(route('gifts.sign-in'))->assertSessionHasErrors('sign_in');

        $this->assertSame(0, $character->refresh()->coupons);
    }

    public function test_consecutive_days_build_the_streak_and_a_gap_resets_it()
    {
        $streaking = $this->ninja(['sign_in_streak' => 3, 'signed_in_on' => Carbon::yesterday()]);
        $this->actingAs($streaking->user)->post(route('gifts.sign-in'));
        $this->assertSame(4, $streaking->refresh()->sign_in_streak);
        $this->assertSame(config('game.sign_in.coupons')[3], $streaking->coupons);

        $lapsed = $this->ninja(['sign_in_streak' => 5, 'signed_in_on' => Carbon::today()->subDays(2)]);
        $this->actingAs($lapsed->user)->post(route('gifts.sign-in'));
        $this->assertSame(1, $lapsed->refresh()->sign_in_streak);
    }

    public function test_the_week_starts_over_after_day_seven()
    {
        $character = $this->ninja(['sign_in_streak' => 7, 'signed_in_on' => Carbon::yesterday()]);
        $this->actingAs($character->user);

        $this->post(route('gifts.sign-in'));

        $this->assertSame(1, $character->refresh()->sign_in_streak);
    }
}

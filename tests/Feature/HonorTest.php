<?php

namespace Tests\Feature;

use App\Game\Leveling;
use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HonorTest extends TestCase
{
    use RefreshDatabase;

    private function ninja(array $overrides = []): Character
    {
        return Character::factory()->create(['level' => 1, 'exp' => 0, ...$overrides]);
    }

    public function test_the_rank_follows_total_honor()
    {
        $perRank = config('game.honor.per_rank');

        $this->assertSame(1, $this->ninja(['honor' => 0])->honorRank());
        $this->assertSame(2, $this->ninja(['honor' => $perRank])->honorRank());
        $this->assertSame(count(config('game.honor.exchange')), $this->ninja(['honor' => 999999])->honorRank());
    }

    public function test_exchanging_medals_pays_the_ranks_exp()
    {
        $perRank = config('game.honor.per_rank');
        $character = $this->ninja(['honor' => 2 * $perRank, 'medals' => 500]);
        [$medals, $exp] = config('game.honor.exchange')[2]; // rank 3

        $this->actingAs($character->user)->post(route('honor.exchange'))->assertRedirect(route('honor.show'));

        $character->refresh();
        $this->assertSame(500 - $medals, $character->medals);
        $this->assertSame(2 * $perRank, $character->honor);
        $this->assertSame(1, $character->honor_exchanges);
        $this->assertGreaterThan(1, $character->level);
        $this->assertSame($exp, $this->totalExp($character));
    }

    public function test_exchanges_need_medals_and_are_limited_per_day()
    {
        $poor = $this->ninja(['medals' => 10]);
        $this->actingAs($poor->user)->post(route('honor.exchange'))->assertSessionHasErrors('medals');

        $limit = config('game.honor.daily_exchanges');
        $character = $this->ninja(['medals' => 99999, 'honor_exchanges' => $limit, 'honor_exchanged_on' => Carbon::today()]);
        $this->actingAs($character->user)->post(route('honor.exchange'))->assertSessionHasErrors('honor');

        // A new day resets the count.
        $character->forceFill(['honor_exchanged_on' => Carbon::yesterday()])->save();
        $this->actingAs($character->user)->post(route('honor.exchange'))->assertSessionHasNoErrors();
        $this->assertSame(1, $character->refresh()->honor_exchanges);
    }

    public function test_the_honor_page_shows_rank_medals_and_exchanges_left()
    {
        $character = $this->ninja(['honor' => 10, 'medals' => 7]);

        $this->actingAs($character->user)->get(route('honor.show'))->assertInertia(fn (Assert $page) => $page
            ->component('character/honor')
            ->where('honor', 10)
            ->where('medals', 7)
            ->where('rank', 1)
            ->where('exchangesLeft', config('game.honor.daily_exchanges'))
            ->has('ranks', count(config('game.honor.exchange'))));
    }

    private function totalExp(Character $character): int
    {
        $total = $character->exp;
        for ($level = 1; $level < $character->level; $level++) {
            $total += Leveling::expToNext($level);
        }

        return $total;
    }
}

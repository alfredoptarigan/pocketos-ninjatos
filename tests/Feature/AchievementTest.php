<?php

namespace Tests\Feature;

use App\Actions\ClaimDailySignIn;
use App\Models\Achievement;
use App\Models\Character;
use App\Models\Title;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AchievementTest extends TestCase
{
    use RefreshDatabase;

    private function achievement(int $id, string $counter, int $target, int $points = 5, ?string $title = null): Achievement
    {
        return Achievement::query()->create([
            'id' => $id, 'name' => "Achievement {$id}", 'counter' => $counter,
            'target' => $target, 'points' => $points, 'title' => $title,
        ]);
    }

    private function ninja(array $overrides = []): Character
    {
        return Character::factory()->create(['avatar' => '0_3', 'level' => 20, 'gold' => 5000, ...$overrides]);
    }

    public function test_reaching_a_level_completes_its_achievement_and_grants_the_title()
    {
        Title::query()->create(['id' => 1, 'code' => 'EffortTitle01', 'name' => 'Ninja Student', 'category' => 1, 'bonus' => []]);
        $this->achievement(1010101, 'level', 21, 7, 'EffortTitle01');
        $character = $this->ninja();

        $character->forceFill(['level' => 21])->save();

        $this->assertNotNull($character->achievements()->first()?->pivot->completed_at);
        $this->assertSame(['EffortTitle01'], $character->titles()->pluck('code')->all());
    }

    public function test_spending_gold_adds_up_but_earning_does_not()
    {
        $this->achievement(1020210, 'gold_spent', 1000);
        $character = $this->ninja();

        $character->forceFill(['gold' => 4400])->save();
        $character->forceFill(['gold' => 9000])->save();
        $this->assertSame(600, $character->refresh()->gold_spent);
        $this->assertSame(0, $character->achievements()->count());

        $character->forceFill(['gold' => 8600])->save();
        $this->assertSame(1000, $character->refresh()->gold_spent);
        $this->assertSame(1, $character->achievements()->count());
    }

    public function test_points_achievements_follow_from_other_achievements()
    {
        $this->achievement(1010101, 'level', 21, 7);
        $this->achievement(1010102, 'level', 22, 7);
        $this->achievement(5150753, 'points', 14, 12);
        $this->achievement(5150754, 'points', 26, 12);
        $character = $this->ninja();

        $character->forceFill(['level' => 22])->save();

        $this->assertSame([1010101, 1010102, 5150753, 5150754], $character->achievements()->orderBy('achievements.id')->pluck('achievements.id')->all());
    }

    public function test_signing_in_counts_the_days()
    {
        $this->achievement(5000749, 'sign_in_days', 1);
        $character = $this->ninja();

        app(ClaimDailySignIn::class)->handle($character);

        $this->assertSame(1, $character->refresh()->sign_in_days);
        $this->assertSame(1, $character->achievements()->count());
    }

    public function test_the_achievements_page_shows_progress_and_catches_up()
    {
        $this->achievement(1010101, 'level', 21, 7);
        $this->achievement(1020210, 'gold_spent', 1000, 6);
        // Already level 30 before achievements existed: the page completes it.
        $character = $this->ninja(['level' => 30]);

        $this->actingAs($character->user)->get(route('achievements.index'))->assertInertia(fn (Assert $page) => $page
            ->component('character/achievements')
            ->has('achievements', 2)
            ->where('achievements.0.progress', 21)
            ->whereNot('achievements.0.completed_at', null)
            ->where('achievements.1.progress', 0)
            ->where('achievements.1.goal', 'Spend 1,000 gold.')
            ->where('points', 7));
    }
}

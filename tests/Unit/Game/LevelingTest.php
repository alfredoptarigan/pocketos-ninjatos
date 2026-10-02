<?php

namespace Tests\Unit\Game;

use App\Game\Leveling;
use Tests\TestCase;

class LevelingTest extends TestCase
{
    public function test_experience_needed_grows_with_level()
    {
        $this->assertSame(120, Leveling::expToNext(1));
        $this->assertSame(209, Leveling::expToNext(2));
    }

    public function test_gaining_experience_can_cross_several_levels()
    {
        // 120 to reach 2, 209 to reach 3, then 10 left over.
        $this->assertSame([3, 10], Leveling::gain(level: 1, exp: 0, gained: 339));
    }

    public function test_levels_stop_at_the_cap()
    {
        [$level, $exp] = Leveling::gain(level: config('game.combat.max_level'), exp: 0, gained: 1_000_000);

        $this->assertSame(config('game.combat.max_level'), $level);
        $this->assertSame(0, $exp);
    }
}

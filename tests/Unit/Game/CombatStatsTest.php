<?php

namespace Tests\Unit\Game;

use App\Game\CombatStats;
use App\Models\Character;
use Tests\TestCase;

class CombatStatsTest extends TestCase
{
    public function test_a_level_one_ninja_matches_the_recorded_fights()
    {
        $stats = CombatStats::for(new Character(['avatar' => '0_12']));

        $this->assertSame(110, $stats->maxHp);
        $this->assertSame(56, $stats->maxMp);
        $this->assertSame(20, $stats->minAttack);
        $this->assertSame(25, $stats->maxAttack);
        $this->assertSame(0, $stats->defense);
        $this->assertSame(5, $stats->crit);
    }

    public function test_stats_grow_with_the_avatars_aptitudes()
    {
        $character = new Character(['avatar' => '0_12']);
        $character->level = 11;

        $stats = CombatStats::for($character);

        $this->assertSame(110 + 10 * 24, $stats->maxHp);
        $this->assertSame(20 + 35, $stats->minAttack);
        $this->assertSame(40, $stats->defense);
        $this->assertSame(6, $stats->dodge);
    }
}

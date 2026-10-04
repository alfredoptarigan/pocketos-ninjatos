<?php

namespace Tests\Unit\Game;

use App\Game\BattleSimulator;
use App\Game\Combatant;
use App\Game\Skill;
use App\Game\Ultimate;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\TestCase;

class UltimateTest extends TestCase
{
    private function fighter(array $overrides = []): Combatant
    {
        return new Combatant(...[
            'name' => 'Fighter', 'hp' => 100, 'maxHp' => 100, 'minAttack' => 1, 'maxAttack' => 1,
            'defense' => 0, 'dodge' => 0, 'crit' => 0, 'critMultiplier' => 200, 'parry' => 0,
            'counter' => 0, 'priority' => 0, 'mp' => 100, 'maxMp' => 100, 'skills' => [], ...$overrides,
        ]);
    }

    private function events(Combatant $a, Combatant $b): array
    {
        return (new BattleSimulator(new Randomizer(new Mt19937(1))))->simulate($a, $b)['events'];
    }

    private function alwaysUnleashed(): void
    {
        config(['skills.ultimate.chance' => 100, 'skills.ultimate.max_chance' => 100]);
    }

    public function test_every_outfit_has_its_ultimate_and_an_upgraded_one_from_plus_19()
    {
        $this->assertSame('1901', Ultimate::for('0_1', 0)->id);
        $this->assertFalse(Ultimate::for('0_1', 18)->upgraded);
        $this->assertTrue(Ultimate::for('0_1', 19)->upgraded);
        $this->assertSame('2003', Ultimate::for('0_103', 0)->id);
    }

    public function test_a_higher_outfit_level_than_the_opponent_raises_the_chance_within_bounds()
    {
        $this->assertSame(30, Ultimate::for('0_1', 0)->chanceAgainst(null));
        $this->assertSame(40, Ultimate::for('0_1', 5)->chanceAgainst(null));
        $this->assertSame(36, Ultimate::for('0_1', 5)->chanceAgainst(Ultimate::for('0_46', 2)));
        $this->assertSame(60, Ultimate::for('0_1', 27)->chanceAgainst(null));
        $this->assertSame(10, Ultimate::for('0_1', 0)->chanceAgainst(Ultimate::for('0_46', 27)));
    }

    public function test_the_ultimate_finishes_a_low_opponent_whatever_their_dodge()
    {
        $this->alwaysUnleashed();
        $ninja = $this->fighter(['ultimate' => Ultimate::for('0_1', 19)]);

        $events = $this->events($ninja, $this->fighter(['hp' => 10, 'dodge' => 100, 'minAttack' => 0, 'maxAttack' => 0]));

        $this->assertSame(
            ['type' => 'ultimate', 'actor' => 0, 'target' => 1, 'skill' => '1901', 'upgraded' => true, 'damage' => 10, 'targetHp' => 0],
            $events[0],
        );
        $this->assertSame(0, last($events)['winner']);
    }

    public function test_the_ultimate_waits_until_the_opponent_is_low_enough()
    {
        $this->alwaysUnleashed();
        config(['skills.ultimate.below_health_percent' => 10]);
        $ninja = $this->fighter(['ultimate' => Ultimate::for('0_1', 0)]);

        $events = $this->events($ninja, $this->fighter(['hp' => 12, 'minAttack' => 0, 'maxAttack' => 0]));
        $mine = array_values(array_filter($events, fn ($event) => ($event['actor'] ?? null) === 0));

        $this->assertSame(['attack', 'attack', 'ultimate'], array_column(array_slice($mine, 0, 3), 'type'));
        $this->assertSame(10, $mine[2]['damage']);
    }

    public function test_a_revive_jutsu_still_brings_the_target_back()
    {
        $this->alwaysUnleashed();
        $revive = Skill::fromArray('3803', [
            'name' => 'Creation Rebirth', 'school' => 'Test', 'kind' => 'revive', 'chance' => 100, 'power' => 25,
            'chakra' => 0, 'level' => 1, 'requires' => null, 'description' => '',
        ]);

        $events = $this->events(
            $this->fighter(['ultimate' => Ultimate::for('0_1', 0)]),
            $this->fighter(['hp' => 5, 'skills' => [$revive], 'minAttack' => 0, 'maxAttack' => 0]),
        );

        $this->assertSame(['ultimate', 'revive'], array_column(array_slice($events, 0, 2), 'type'));
    }
}

<?php

namespace Tests\Unit\Game;

use App\Game\BattleSimulator;
use App\Game\Combatant;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\TestCase;

class BattleSimulatorTest extends TestCase
{
    private function fighter(array $overrides = []): Combatant
    {
        return new Combatant(...[
            'name' => 'Fighter',
            'hp' => 100,
            'maxHp' => 100,
            'minAttack' => 10,
            'maxAttack' => 10,
            'defense' => 0,
            'dodge' => 0,
            'crit' => 0,
            'critMultiplier' => 200,
            'parry' => 0,
            'counter' => 0,
            'priority' => 0,
            ...$overrides,
        ]);
    }

    private function simulate(Combatant $a, Combatant $b, int $seed = 1): array
    {
        return (new BattleSimulator(new Randomizer(new Mt19937($seed))))->simulate($a, $b);
    }

    private function attacks(array $result): array
    {
        return array_values(array_filter($result['events'], fn ($e) => in_array($e['type'], ['attack', 'counter'])));
    }

    public function test_the_stronger_fighter_wins_and_the_log_ends_with_the_result()
    {
        $result = $this->simulate($this->fighter(['minAttack' => 50, 'maxAttack' => 50]), $this->fighter());

        $this->assertSame(0, $result['winner']);
        $this->assertSame(['type' => 'end', 'winner' => 0, 'reason' => 'ko'], end($result['events']));
        $this->assertSame(0, $result['hp'][1]);
    }

    public function test_same_seed_gives_the_same_battle()
    {
        $a = $this->fighter(['minAttack' => 5, 'maxAttack' => 15, 'dodge' => 20, 'crit' => 20]);
        $b = $this->fighter(['minAttack' => 5, 'maxAttack' => 15, 'dodge' => 20, 'crit' => 20]);

        $this->assertSame($this->simulate($a, $b, 42), $this->simulate($a, $b, 42));
    }

    public function test_attackers_take_turns_starting_with_the_challenger()
    {
        $actors = array_column($this->attacks($this->simulate($this->fighter(), $this->fighter())), 'actor');

        $this->assertSame([0, 1, 0, 1], array_slice($actors, 0, 4));
    }

    public function test_first_strike_lets_the_defender_act_first()
    {
        $result = $this->simulate($this->fighter(), $this->fighter(['priority' => 100]));

        $this->assertSame(1, $this->attacks($result)[0]['actor']);
    }

    public function test_defense_reduces_damage()
    {
        $hit = $this->attacks($this->simulate($this->fighter(), $this->fighter(['defense' => 500])))[0];

        $this->assertSame(5, $hit['damage']); // 10 * (1 - 500 / (500 + 500))
    }

    public function test_critical_hits_multiply_damage()
    {
        $hit = $this->attacks($this->simulate($this->fighter(['crit' => 100, 'critMultiplier' => 230]), $this->fighter()))[0];

        $this->assertTrue($hit['crit']);
        $this->assertSame(23, $hit['damage']);
    }

    public function test_parry_halves_damage()
    {
        $hit = $this->attacks($this->simulate($this->fighter(), $this->fighter(['parry' => 100])))[0];

        $this->assertTrue($hit['parried']);
        $this->assertSame(5, $hit['damage']);
    }

    public function test_dodged_attacks_deal_no_damage()
    {
        $hit = $this->attacks($this->simulate($this->fighter(), $this->fighter(['dodge' => 100])))[0];

        $this->assertFalse($hit['hit']);
        $this->assertSame(0, $hit['damage']);
        $this->assertSame(100, $hit['targetHp']);
    }

    public function test_defenders_can_counter_after_surviving_a_hit()
    {
        $events = $this->simulate($this->fighter(), $this->fighter(['counter' => 100]))['events'];
        $counter = array_values(array_filter($events, fn ($e) => $e['type'] === 'counter'))[0];

        $this->assertSame(1, $counter['actor']);
        $this->assertSame(0, $counter['target']);
    }

    public function test_damage_is_at_least_one_and_health_never_goes_negative()
    {
        $result = $this->simulate($this->fighter(['minAttack' => 1, 'maxAttack' => 1]), $this->fighter(['hp' => 3, 'maxHp' => 3, 'defense' => 100000]));

        foreach ($this->attacks($result) as $hit) {
            $this->assertGreaterThanOrEqual(1, $hit['damage']);
            $this->assertGreaterThanOrEqual(0, $hit['targetHp']);
        }
        $this->assertSame(0, $result['winner']);
    }

    public function test_a_stalemate_goes_to_the_side_with_more_health_left()
    {
        $result = $this->simulate(
            $this->fighter(['dodge' => 100, 'hp' => 90]),
            $this->fighter(['dodge' => 100, 'hp' => 50]),
        );

        $this->assertSame(['type' => 'end', 'winner' => 0, 'reason' => 'timeout'], end($result['events']));
    }
}

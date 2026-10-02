<?php

namespace Tests\Unit\Game;

use App\Game\BattleSimulator;
use App\Game\Combatant;
use App\Game\Skill;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\TestCase;

class BattleSkillsTest extends TestCase
{
    private function skill(string $kind, array $overrides = []): Skill
    {
        return Skill::fromArray('9000', [
            'name' => 'Test Jutsu', 'school' => 'Test', 'kind' => $kind, 'chance' => 100, 'power' => 200,
            'chakra' => 0, 'level' => 1, 'requires' => null, 'description' => '', ...$overrides,
        ]);
    }

    private function fighter(array $overrides = []): Combatant
    {
        return new Combatant(...[
            'name' => 'Fighter', 'hp' => 100, 'maxHp' => 100, 'minAttack' => 10, 'maxAttack' => 10,
            'defense' => 0, 'dodge' => 0, 'crit' => 0, 'critMultiplier' => 200, 'parry' => 0,
            'counter' => 0, 'priority' => 0, 'mp' => 100, 'maxMp' => 100, 'skills' => [], ...$overrides,
        ]);
    }

    private function events(Combatant $a, Combatant $b): array
    {
        return (new BattleSimulator(new Randomizer(new Mt19937(1))))->simulate($a, $b)['events'];
    }

    private function first(array $events, string $type): array
    {
        return array_values(array_filter($events, fn ($e) => $e['type'] === $type))[0];
    }

    public function test_strike_jutsu_replace_the_normal_attack_and_cost_chakra()
    {
        $events = $this->events($this->fighter(['skills' => [$this->skill('strike', ['chakra' => 100])]]), $this->fighter());
        $hit = $this->first($events, 'attack');

        $this->assertSame('9000', $hit['skill']);
        $this->assertSame(20, $hit['damage']); // 10 * 200%
        $this->assertSame(10, $hit['mpCost']); // ceil(100 * 100 max chakra / 1000)
        $this->assertSame(90, $hit['actorMp']);
    }

    public function test_jutsu_need_enough_chakra()
    {
        $events = $this->events($this->fighter(['mp' => 5, 'skills' => [$this->skill('strike', ['chakra' => 100])]]), $this->fighter());

        $this->assertArrayNotHasKey('skill', $this->first($events, 'attack'));
    }

    public function test_lifesteal_and_recoil_change_the_users_health()
    {
        $drain = $this->first($this->events($this->fighter(['hp' => 50, 'skills' => [$this->skill('strike', ['lifesteal' => 50])]]), $this->fighter()), 'attack');
        $recoil = $this->first($this->events($this->fighter(['skills' => [$this->skill('strike', ['self_damage' => 30])]]), $this->fighter()), 'attack');

        $this->assertSame(60, $drain['actorHp']);  // healed half of 20
        $this->assertSame(94, $recoil['actorHp']); // lost 30% of 20
    }

    public function test_stunned_fighters_lose_their_turns()
    {
        $events = $this->events($this->fighter(['skills' => [$this->skill('strike', ['stun' => 2, 'max_uses' => 1])]]), $this->fighter());

        $this->assertSame(['type' => 'stunned', 'actor' => 1], $events[1]);
        $this->assertSame(['type' => 'stunned', 'actor' => 1], $events[3]);
    }

    public function test_exhausting_jutsu_stun_their_user()
    {
        $events = $this->events($this->fighter(['skills' => [$this->skill('strike', ['self_stun' => 1, 'max_uses' => 1])]]), $this->fighter());

        $this->assertSame(['type' => 'stunned', 'actor' => 0], $events[2]);
    }

    public function test_follow_up_and_extra_attacks_add_hits()
    {
        $follow = $this->events($this->fighter(['skills' => [$this->skill('follow_up', ['power' => 80])]]), $this->fighter());
        $extra = $this->events($this->fighter(['skills' => [$this->skill('extra', ['power' => 65])]]), $this->fighter());

        $this->assertSame(['attack', 'follow_up'], [$follow[0]['type'], $follow[1]['type']]);
        $this->assertSame(8, $follow[1]['damage']);
        $this->assertSame(['extra', 'attack'], [$extra[0]['type'], $extra[1]['type']]);
        $this->assertSame(7, $extra[0]['damage']); // 6.5 rounds to 7
    }

    public function test_counter_jutsu_strike_back_when_attacked()
    {
        $events = $this->events($this->fighter(), $this->fighter(['skills' => [$this->skill('counter', ['power' => 144])]]));
        $counter = $this->first($events, 'counter');

        $this->assertSame(1, $counter['actor']);
        $this->assertSame('9000', $counter['skill']);
        $this->assertSame(14, $counter['damage']);
    }

    public function test_block_cancels_an_attack()
    {
        $hit = $this->first($this->events($this->fighter(), $this->fighter(['skills' => [$this->skill('block')]])), 'attack');

        $this->assertTrue($hit['blocked']);
        $this->assertSame(0, $hit['damage']);
        $this->assertSame(100, $hit['targetHp']);
    }

    public function test_reflect_returns_damage_to_the_attacker()
    {
        $events = $this->events($this->fighter(), $this->fighter(['skills' => [$this->skill('reflect', ['power' => 60])]]));
        $reflect = $this->first($events, 'reflect');

        $this->assertSame(['type' => 'reflect', 'actor' => 1, 'target' => 0, 'skill' => '9000', 'damage' => 6, 'targetHp' => 94], $reflect);
    }

    public function test_heal_restores_part_of_the_lost_health()
    {
        $events = $this->events($this->fighter(['minAttack' => 50, 'maxAttack' => 50]), $this->fighter(['skills' => [$this->skill('heal', ['power' => 20, 'max_uses' => 1])]]));
        $heal = $this->first($events, 'heal');

        $this->assertSame(['type' => 'heal', 'actor' => 1, 'skill' => '9000', 'amount' => 10, 'hp' => 60], $heal);
    }

    public function test_revive_brings_a_knocked_out_fighter_back()
    {
        $events = $this->events(
            $this->fighter(['minAttack' => 500, 'maxAttack' => 500]),
            $this->fighter(['skills' => [$this->skill('revive', ['power' => 25, 'max_uses' => 1])]]),
        );
        $revive = $this->first($events, 'revive');

        $this->assertSame(['type' => 'revive', 'actor' => 1, 'skill' => '9000', 'hp' => 25], $revive);
    }

    public function test_jutsu_respect_their_use_limit()
    {
        $events = $this->events($this->fighter(['skills' => [$this->skill('strike', ['max_uses' => 1])]]), $this->fighter());
        $skilled = array_filter($events, fn ($e) => isset($e['skill']));

        $this->assertCount(1, $skilled);
    }

    public function test_every_configured_skill_loads()
    {
        foreach (array_keys(config('game.skills')) as $id) {
            $this->assertSame((string) $id, Skill::find((string) $id)->id);
        }
    }
}

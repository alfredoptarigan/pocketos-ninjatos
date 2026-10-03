<?php

namespace Tests\Unit\Game;

use App\Game\BattleSimulator;
use App\Game\Combatant;
use App\Game\Skill;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\TestCase;

/**
 * The status effects of config('skills.skills'), with test jutsu that always roll.
 */
class JutsuEffectsTest extends TestCase
{
    private function jutsu(string $kind, ?string $effect = null, array $overrides = []): Skill
    {
        static $next = 9100;

        return Skill::fromArray((string) $next++, [
            'name' => 'Test', 'school' => 'body', 'kind' => $kind, 'chance' => 100, 'power' => 100,
            'chakra' => 0, 'requires' => null, 'effect' => $effect, ...$overrides,
        ]);
    }

    private function fighter(array $overrides = []): Combatant
    {
        return new Combatant(...[
            'name' => 'Fighter', 'hp' => 1000, 'maxHp' => 1000, 'minAttack' => 10, 'maxAttack' => 10,
            'defense' => 0, 'dodge' => 0, 'crit' => 0, 'critMultiplier' => 200, 'parry' => 0,
            'counter' => 0, 'priority' => 0, 'mp' => 1000, 'maxMp' => 1000, 'skills' => [], ...$overrides,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function events(Combatant $a, Combatant $b, int $seed = 1): array
    {
        return (new BattleSimulator(new Randomizer(new Mt19937($seed))))->simulate($a, $b)['events'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function of(array $events, string $type, array $match = []): array
    {
        return array_values(array_filter($events, fn ($e) => $e['type'] === $type && array_intersect_assoc($match, $e) === $match));
    }

    public function test_burn_ticks_on_the_target_before_its_moves()
    {
        $fireball = $this->jutsu('strike', 'burn', ['school' => 'fire', 'power' => 100, 'amount' => 60, 'turns' => 3, 'max_uses' => 1]);
        $events = $this->events($this->fighter(['skills' => [$fireball]]), $this->fighter());

        $this->assertSame(3, $this->of($events, 'status', ['status' => 'burn', 'actor' => 1])[0]['turns']);
        $ticks = $this->of($events, 'tick', ['status' => 'burn']);
        $this->assertCount(3, $ticks);
        $this->assertSame(2, $ticks[0]['damage']); // 60% of 10 over 3 turns
        $this->assertNotEmpty($this->of($events, 'expire', ['status' => 'burn']));
    }

    public function test_mist_stops_burns_and_can_make_attacks_miss()
    {
        $fireball = $this->jutsu('strike', 'burn', ['school' => 'fire', 'amount' => 60, 'turns' => 3]);
        $mist = $this->jutsu('battle', 'mist', ['amount' => 50, 'cap' => 100]);
        $events = $this->events($this->fighter(['skills' => [$fireball]]), $this->fighter(['skills' => [$mist]]));

        $this->assertSame([], $this->of($events, 'status', ['status' => 'burn']));
        $this->assertNotEmpty(array_filter($this->of($events, 'attack', ['actor' => 0]), fn ($e) => $e['mist'] ?? false));
    }

    public function test_freeze_skips_turns_until_a_hit_that_deals_double()
    {
        $blade = $this->jutsu('strike', 'freeze', ['power' => 0, 'turns' => 5, 'max_uses' => 1]);
        $events = $this->events($this->fighter(['skills' => [$blade]]), $this->fighter());

        $this->assertSame('cast', $events[0]['type']);
        $this->assertSame(['type' => 'stunned', 'actor' => 1, 'reason' => 'freeze'], $events[2]);
        $this->assertSame(20, $events[3]['damage']); // the next hit breaks the ice for double
        $this->assertTrue($events[3]['shatter']);
    }

    public function test_prayer_blocks_and_makes_the_user_untouchable()
    {
        $prayer = $this->jutsu('block', 'invulnerable', ['power' => 0, 'turns' => 2, 'max_uses' => 1]);
        $events = $this->events($this->fighter(), $this->fighter(['skills' => [$prayer]]));
        $attacks = $this->of($events, 'attack', ['actor' => 0]);

        $this->assertTrue($attacks[0]['blocked']);
        $this->assertTrue($attacks[1]['immune']);
        $this->assertSame(0, $attacks[1]['damage']);
        $this->assertSame(10, $attacks[3]['damage']);
    }

    public function test_slow_can_cost_turns()
    {
        $mud = $this->jutsu('before_enemy', 'slow', ['power' => 0, 'amount' => 200, 'turns' => 2, 'max_uses' => 1]);
        $events = $this->events($this->fighter(), $this->fighter(['skills' => [$mud]]));

        $this->assertSame(['type' => 'status', 'actor' => 0, 'status' => 'slow', 'turns' => 2, 'skill' => $mud->id, 'source' => 1], $events[1]);
        $this->assertSame(['type' => 'stunned', 'actor' => 0, 'reason' => 'slow'], $events[2]);
    }

    public function test_clay_explodes_for_the_damage_taken_meanwhile()
    {
        $clay = $this->jutsu('prepare', 'clay', ['power' => 0, 'turns' => 2, 'max_uses' => 1]);
        $events = $this->events($this->fighter(['skills' => [$clay]]), $this->fighter());
        $boom = $this->of($events, 'tick', ['status' => 'clay'])[0];

        $this->assertSame(20, $boom['damage']); // two normal hits of 10
    }

    public function test_earth_prison_drains_chakra_and_lowers_defense()
    {
        $prison = $this->jutsu('follow_up', 'prison', ['power' => 0, 'amount' => 10, 'defense' => 500, 'max_uses' => 1]);
        $events = $this->events($this->fighter(['skills' => [$prison]]), $this->fighter(['defense' => 500]));
        $cast = $this->of($events, 'cast')[0];

        $this->assertSame(900, $cast['targetMp']);
        $this->assertSame(1000, $cast['actorMp']); // full already: the drain cannot overfill
        $this->assertSame(5, $events[0]['damage']); // 10 halved by 500 defense
        $this->assertSame(10, $this->of($events, 'attack', ['actor' => 0])[1]['damage']);
    }

    public function test_static_field_absorbs_damage()
    {
        $field = $this->jutsu('before_enemy', 'shield', ['power' => 0, 'amount' => 1, 'max_uses' => 1]);
        $events = $this->events($this->fighter(), $this->fighter(['skills' => [$field]]));
        $hit = $this->of($events, 'attack', ['actor' => 0])[0];

        $this->assertSame(10, $hit['absorbed']);
        $this->assertSame(1000, $hit['targetHp']);
        $this->assertSame(0, $hit['damage']);
    }

    public function test_thunder_cloud_strikes_a_side_after_each_turn()
    {
        $cloud = $this->jutsu('battle', 'cloud', ['amount' => 7]);
        $strikes = $this->of($this->events($this->fighter(['skills' => [$cloud]]), $this->fighter()), 'cloud');

        $this->assertNotEmpty($strikes);
        $this->assertSame(70, $strikes[0]['damage']);
    }

    public function test_eight_trigram_palm_doubles_the_opponents_chakra_costs()
    {
        $palm = $this->jutsu('strike', 'chakra_burn', ['max_uses' => 1]);
        $fireball = $this->jutsu('strike', null, ['chakra' => 100, 'chance' => 100]);
        $events = $this->events($this->fighter(['skills' => [$palm]]), $this->fighter(['skills' => [$fireball]]));

        $this->assertSame(200, $this->of($events, 'attack', ['actor' => 1])[0]['mpCost']); // 100 * 1000 / 1000, doubled
    }

    public function test_eight_gates_open_when_hurt_and_give_extra_turns()
    {
        $gates = $this->jutsu('hurt', 'gates', ['power' => 0, 'amount' => 200, 'cooldown' => 1]);
        $events = $this->events($this->fighter(), $this->fighter(['skills' => [$gates]]));

        $this->assertNotEmpty($this->of($events, 'status', ['status' => 'gates', 'actor' => 1]));
        $this->assertNotEmpty($this->of($events, 'haste', ['actor' => 1]));
    }

    public function test_puppet_poisons_once()
    {
        $puppet = $this->jutsu('follow_up', 'poison', ['power' => 50, 'amount' => 3, 'turns' => 2]);
        $events = $this->events($this->fighter(['skills' => [$puppet]]), $this->fighter());

        $this->assertCount(1, $this->of($events, 'status', ['status' => 'poison']));
        $this->assertSame(30, $this->of($events, 'tick', ['status' => 'poison'])[0]['damage']);
    }

    public function test_liquor_makes_the_drunk_opponent_miss()
    {
        $liquor = $this->jutsu('battle', 'drunk', ['amount' => 100, 'crit' => 3]);
        $events = $this->events($this->fighter(['skills' => [$liquor]]), $this->fighter());

        $this->assertSame('drunk', $events[0]['status']);
        $this->assertFalse($this->of($events, 'attack', ['actor' => 1])[0]['hit']);
    }

    public function test_snare_lowers_dodge_for_the_attack()
    {
        $snare = $this->jutsu('prepare', 'snare', ['power' => 0, 'amount' => 100]);
        $events = $this->events($this->fighter(['skills' => [$snare]]), $this->fighter(['dodge' => 100]));

        $this->assertTrue($this->of($events, 'attack', ['actor' => 0])[0]['hit']);
    }

    public function test_tailed_beast_heart_removes_a_debuff()
    {
        $puppet = $this->jutsu('follow_up', 'poison', ['power' => 0, 'amount' => 1, 'turns' => 20]);
        $heart = $this->jutsu('prepare', 'cleanse', ['power' => 0, 'max_uses' => 1]);
        $events = $this->events($this->fighter(['skills' => [$puppet]]), $this->fighter(['skills' => [$heart]]));

        $this->assertNotEmpty($this->of($events, 'expire', ['actor' => 1, 'status' => 'poison', 'skill' => $heart->id]));
    }

    public function test_cursed_seal_adds_attack_as_health_drops()
    {
        $seal = $this->jutsu('battle', 'cursed_seal');
        $events = $this->events($this->fighter(), $this->fighter(['hp' => 500, 'skills' => [$seal]]));

        $this->assertSame(13, $this->of($events, 'attack', ['actor' => 1])[0]['damage']); // 50% lost: +25%
    }

    public function test_five_element_seal_blocks_element_jutsu()
    {
        $seal = $this->jutsu('strike', 'seal', ['turns' => 10, 'max_uses' => 1]);
        $fireball = $this->jutsu('strike', null, ['school' => 'fire']);
        $events = $this->events($this->fighter(['skills' => [$seal]]), $this->fighter(['skills' => [$fireball]]));

        $this->assertArrayNotHasKey('skill', $this->of($events, 'attack', ['actor' => 1])[0]);
    }

    public function test_dead_demon_seal_weakens_the_opponents_damage()
    {
        $seal = $this->jutsu('strike', 'dead_demon', ['amount' => 70, 'body' => 95, 'turns' => 10, 'max_uses' => 1]);
        $events = $this->events($this->fighter(['skills' => [$seal]]), $this->fighter());

        $this->assertSame(3, $this->of($events, 'attack', ['actor' => 1])[0]['damage']);
    }

    public function test_sexy_technique_needs_a_buff_on_the_opponent()
    {
        $sexy = $this->jutsu('strike', 'charm', ['power' => 0, 'turns' => 2, 'max_uses' => 1]);
        $mist = $this->jutsu('battle', 'mist', ['amount' => 0, 'cap' => 0]);

        $plain = $this->events($this->fighter(['skills' => [$sexy]]), $this->fighter());
        $buffed = $this->events($this->fighter(['skills' => [$sexy]]), $this->fighter(['skills' => [$mist]]));

        $this->assertSame([], $this->of($plain, 'cast'));
        $this->assertSame(['type' => 'stunned', 'actor' => 1, 'reason' => 'charm'], $this->of($buffed, 'stunned')[0]);
    }

    public function test_death_mirage_drains_until_a_chakra_jutsu()
    {
        $mirage = $this->jutsu('follow_up', 'mirage', ['power' => 0, 'amount' => 6, 'max_uses' => 1]);
        $events = $this->events($this->fighter(['skills' => [$mirage]]), $this->fighter());
        $tick = $this->of($events, 'tick', ['status' => 'mirage'])[0];

        $this->assertSame(60, $tick['damage']);
        $this->assertSame(940, $tick['mp']);
    }

    public function test_pre_healing_regenerates_and_shares_uses_with_mystical_palm()
    {
        $regen = $this->jutsu('hurt', 'regen', ['power' => 0, 'amount' => 3, 'turns' => 2, 'max_uses' => 1, 'uses_group' => 'medical']);
        $palm = $this->jutsu('heal', null, ['power' => 10, 'max_uses' => 1, 'uses_group' => 'medical']);
        $events = $this->events($this->fighter(), $this->fighter(['hp' => 900, 'skills' => [$regen, $palm]]));

        $this->assertCount(2, $this->of($events, 'tick', ['status' => 'regen']));
        $this->assertSame([], $this->of($events, 'heal'));
    }

    public function test_chakra_blade_cuts_the_opponents_chakra()
    {
        $blade = $this->jutsu('strike', 'chakra_cut', ['amount' => 15, 'max_uses' => 1]);
        $hit = $this->events($this->fighter(['skills' => [$blade]]), $this->fighter())[0];

        $this->assertSame(850, $hit['targetMp']);
    }

    public function test_giant_waterfall_needs_a_debuff_on_its_user()
    {
        $puppet = $this->jutsu('follow_up', 'poison', ['power' => 0, 'amount' => 0, 'turns' => 20]);
        $fall = $this->jutsu('before_enemy', 'waterfall', ['power' => 0, 'turns' => 1, 'max_uses' => 1]);
        $events = $this->events($this->fighter(['skills' => [$puppet]]), $this->fighter(['skills' => [$fall]]));

        $cast = $this->of($events, 'cast', ['actor' => 1])[0];
        $this->assertSame($fall->id, $cast['skill']);
        $this->assertNotEmpty($this->of($events, 'stunned', ['actor' => 0]));
    }

    public function test_sunset_doubles_element_jutsu()
    {
        $sunset = $this->jutsu('battle', 'sunset', ['amount' => 100, 'cap' => 100]);
        $fireball = $this->jutsu('strike', null, ['school' => 'fire']);
        $events = $this->events($this->fighter(['skills' => [$sunset, $fireball]]), $this->fighter());
        $hit = $this->of($events, 'attack', ['actor' => 0])[1];

        $this->assertTrue($hit['double']);
        $this->assertSame(20, $hit['damage']);
    }

    public function test_bloodboil_makes_chakra_jutsu_recoil()
    {
        $boil = $this->jutsu('prepare', 'bloodboil', ['power' => 0, 'amount' => 10, 'recoil' => 50, 'turns' => 5, 'max_uses' => 1]);
        $fireball = $this->jutsu('strike', null, ['chakra' => 10]);
        $hit = $this->of($this->events($this->fighter(['skills' => [$boil, $fireball]]), $this->fighter()), 'attack', ['actor' => 0])[0];

        $this->assertSame(995, $hit['actorHp']); // half of 10 recoils
    }

    public function test_revive_does_not_work_while_unable_to_move()
    {
        $crush = $this->jutsu('strike', null, ['power' => 100000, 'stun' => 2]);
        $revive = $this->jutsu('revive', null, ['power' => 25, 'max_uses' => 1]);
        $events = $this->events($this->fighter(['skills' => [$crush]]), $this->fighter(['skills' => [$revive]]));

        $this->assertSame([], $this->of($events, 'revive'));
    }

    public function test_battle_jutsu_need_their_chakra_at_the_start()
    {
        $cloud = $this->jutsu('battle', 'cloud', ['amount' => 7, 'chakra' => 1000]);
        $events = $this->events($this->fighter(['mp' => 10, 'skills' => [$cloud]]), $this->fighter());

        $this->assertSame([], $this->of($events, 'cloud'));
    }
}

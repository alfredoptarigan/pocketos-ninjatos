<?php

namespace App\Game;

use App\Game\Concerns\AppliesJutsuEffects;
use App\Game\Concerns\TracksStatuses;
use Closure;
use Random\Randomizer;

/**
 * Turn-based auto battle in the spirit of the original server: the two sides
 * alternate actions until one falls, and the client replays the log.
 *
 * Side 0 is the challenger, side 1 the defender. Jutsu (App\Game\Skill) hook
 * in at the start ('battle'), before the opponent moves ('before_enemy'),
 * before the action ('prepare', 'extra'), as the action ('strike'), after it
 * ('follow_up'), when attacked ('block', 'counter', 'reflect', 'heal',
 * 'hurt') and on knock-out ('revive'). Their effects become statuses
 * (TracksStatuses, AppliesJutsuEffects). Without jutsu the random rolls happen
 * in exactly the old order.
 */
final class BattleSimulator
{
    use AppliesJutsuEffects;
    use TracksStatuses;

    private const PARRY_DAMAGE_PERCENT = 50;

    // Dead Demon Seal hits these harder: body jutsu and Assassinate (lifesteal).
    private const BODY_SCHOOL = 'body';

    /** @var array{0: Combatant, 1: Combatant} */
    private array $fighters;

    /** @var array{0: int, 1: int} */
    private array $hp;

    /** @var array{0: int, 1: int} */
    private array $mp;

    /** @var array{0: int, 1: int} turns each side still has to skip */
    private array $stun;

    /** @var array{0: array<string, int>, 1: array<string, int>} jutsu (or shared group) uses this battle */
    private array $uses;

    /** @var list<array<string, mixed>> */
    private array $events;

    private int $turn;

    private int $lastHaste;

    public function __construct(private readonly Randomizer $random) {}

    /**
     * @return array{winner: int, events: list<array<string, mixed>>, hp: array{0: int, 1: int}, mp: array{0: int, 1: int}}
     */
    public function simulate(Combatant $challenger, Combatant $defender): array
    {
        $this->fighters = [$challenger, $defender];
        $this->hp = [$challenger->hp, $defender->hp];
        $this->mp = [$challenger->mp, $defender->mp];
        $this->stun = [0, 0];
        $this->uses = [[], []];
        $this->events = [];
        $this->statuses = [[], []];
        $this->gates = [['open' => 0, 'last' => PHP_INT_MIN], ['open' => 0, 'last' => PHP_INT_MIN]];
        $this->poisoned = [false, false];
        $this->turn = 0;
        $this->lastHaste = PHP_INT_MIN;
        $this->startBattle();
        $actor = $this->chance($defender->priority) ? 1 : 0;

        for (; $this->turn < config('game.combat.max_turns'); $this->turn++) {
            $winner = $this->takeTurn($actor, 1 - $actor);

            if ($winner !== null) {
                return $this->finish($winner, 'ko');
            }

            $actor = $this->hasted($actor) ? $actor : 1 - $actor;
        }

        return $this->finish($this->leaderOnHealth(), 'timeout');
    }

    /**
     * One side's turn; returns the winner if it ends the battle.
     */
    private function takeTurn(int $actor, int $target): ?int
    {
        $winner = $this->act($actor, $target);

        if ($winner !== null) {
            return $winner;
        }

        $this->thunderCloud($actor);
        $this->countDownStatuses($actor);

        return $this->knockout();
    }

    /**
     * Statuses tick, the opponent may act first, then the side moves.
     */
    private function act(int $actor, int $target): ?int
    {
        $this->tickStatuses($actor);

        if (($winner = $this->knockout()) !== null) {
            return $winner;
        }

        if ($first = $this->trigger($target, 'before_enemy')) {
            $this->cast($target, $actor, $first);
        }

        if ($this->losesTurn($actor)) {
            return null;
        }

        if ($prepare = $this->trigger($actor, 'prepare')) {
            $this->cast($actor, $target, $prepare);
        }

        if ($extra = $this->trigger($actor, 'extra')) {
            $this->strike('extra', $actor, $target, $extra);

            if (($winner = $this->knockout()) !== null) {
                return $winner;
            }
        }

        $jutsu = $this->trigger($actor, 'strike');
        $landed = $this->use('attack', $actor, $target, $jutsu);

        if (($winner = $this->knockout()) !== null) {
            return $winner;
        }

        if ($landed && $jutsu === null && ($followUp = $this->trigger($actor, 'follow_up'))) {
            $this->use('follow_up', $actor, $target, $followUp);

            if (($winner = $this->knockout()) !== null) {
                return $winner;
            }
        }

        if ($landed) {
            $counter = $this->trigger($target, 'counter');

            if ($counter || $this->chance($this->fighters[$target]->counter)) {
                $this->strike('counter', $target, $actor, $counter);

                return $this->knockout();
            }
        }

        return null;
    }

    /**
     * A strike, or a cast for effect-only jutsu; returns whether a hit landed.
     *
     * @param  array{skill: Skill, cost: int}|null  $jutsu
     */
    private function use(string $type, int $actor, int $target, ?array $jutsu): bool
    {
        if ($jutsu !== null && $jutsu['skill']->power === 0) {
            $this->cast($actor, $target, $jutsu);

            return false;
        }

        return $this->strike($type, $actor, $target, $jutsu);
    }

    /**
     * Resolve one hit and the defender's reactions; returns whether it landed.
     *
     * @param  array{skill: Skill, cost: int}|null  $jutsu
     */
    private function strike(string $type, int $actor, int $target, ?array $jutsu): bool
    {
        $skill = $jutsu['skill'] ?? null;
        $attacker = $this->fighters[$actor];
        // The hit is logged before anything it causes (expired statuses, reactions).
        $index = count($this->events);
        $this->events[] = [];

        // A thrown-back bomb hits its thrower.
        $backfire = $skill?->backfire && $this->chance($this->backfireChance());
        if ($backfire) {
            $target = $actor;
        }

        $defender = $this->fighters[$target];
        $dodged = $this->chance($this->dodgeAgainst($actor, $target));
        $misted = ! $dodged && $this->lostInMist($target);
        $hit = ! $dodged && ! $misted;
        $immune = $hit && $this->has($target, 'invulnerable');
        $block = $hit && ! $immune && ! $backfire ? $this->trigger($target, 'block') : null;
        $blocked = $block !== null;
        $struck = $hit && ! $blocked && ! $immune;
        $crit = $struck && $this->chance($attacker->crit + ($this->statuses[$target]['drunk']['crit'] ?? 0));
        $parried = $struck && $this->chance($defender->parry);
        [$damage, $notes] = $struck ? $this->damage($actor, $target, $skill, $crit, $parried) : [0, []];
        $this->hp[$target] = max(0, $this->hp[$target] - $damage);

        if (isset($this->statuses[$target]['clay'])) {
            $this->statuses[$target]['clay']['stored'] += $damage;
        }

        $event = [
            'type' => $type,
            'actor' => $actor,
            'target' => $target,
            'hit' => $hit,
            'crit' => $crit,
            'parried' => $parried,
            'blocked' => $blocked,
            'damage' => $damage,
            'targetHp' => $this->hp[$target],
            ...$notes,
            ...($immune ? ['immune' => true] : []),
            ...($misted ? ['mist' => true] : []),
        ];

        if ($block) {
            $event['blockSkill'] = $block['skill']->id;
        }

        if ($skill) {
            $event = [...$event, 'skill' => $skill->id, 'mpCost' => $jutsu['cost'], 'actorMp' => $this->mp[$actor]];
        }

        $this->events[$index] = $event;

        if ($block) {
            $this->events[$index] = [...$this->events[$index], ...$this->applyEffect($block['skill'], $target, $actor, 0)];
        }

        if ($skill) {
            $this->events[$index] = [...$this->events[$index], ...$this->jutsuEffects($skill, $actor, $target, $damage, $backfire, $struck)];
        }

        if ($damage > 0 && ! $backfire) {
            $this->react($target, $actor, $damage);
        }

        return $hit && ! $blocked;
    }

    /**
     * Side effects of an offensive jutsu on the user and the target.
     *
     * @return array<string, mixed>
     */
    private function jutsuEffects(Skill $skill, int $actor, int $target, int $damage, bool $backfire, bool $struck): array
    {
        $effects = $backfire ? ['backfire' => true] : [];
        $maxHp = $this->fighters[$actor]->maxHp;

        if ($skill->lifesteal > 0 && $damage > 0) {
            $this->hp[$actor] = min($maxHp, $this->hp[$actor] + $this->healed($actor, (int) round($damage * $skill->lifesteal / 100)));
            $effects['actorHp'] = $this->hp[$actor];
        }

        $recoil = $skill->selfDamage + ($skill->chakra > 0 ? ($this->statuses[$actor]['bloodboil']['recoil'] ?? 0) : 0);

        if ($recoil > 0 && $damage > 0) {
            $this->hp[$actor] = max(0, $this->hp[$actor] - (int) round($damage * $recoil / 100));
            $effects['actorHp'] = $this->hp[$actor];
        }

        if ($skill->stun > 0 && $damage > 0 && ! $backfire) {
            $this->stun[$target] += $skill->stun;
        }

        if ($skill->selfStun > 0) {
            $this->stun[$actor] += $skill->selfStun;
        }

        if ($skill->school === 'lightning' && $damage > 0) {
            // Lightning breaks a charm and recharges the user's Static Field.
            $this->removeStatus($target, 'charm', $skill->id);
            $this->rechargeShield($actor, intdiv($damage, 3));
        }

        // A knocked-out target takes no more statuses.
        if ($struck && ! $backfire && $this->hp[$target] > 0) {
            $effects = [...$effects, ...$this->applyEffect($skill, $actor, $target, $damage)];
        }

        return $effects;
    }

    private function rechargeShield(int $side, int $points): void
    {
        if ($this->has($side, 'shield') && $points > 0) {
            $shield = &$this->statuses[$side]['shield'];
            $shield['amount'] = min($shield['max'], $shield['amount'] + $points);
        }
    }

    /**
     * Revive anyone knocked out who can; returns the winner if someone stays down.
     */
    private function knockout(): ?int
    {
        foreach ([0, 1] as $side) {
            if ($this->hp[$side] === 0 && ($revive = $this->trigger($side, 'revive'))) {
                $this->hp[$side] = (int) round($this->fighters[$side]->maxHp * $revive['skill']->power / 100);
                $this->events[] = ['type' => 'revive', 'actor' => $side, 'skill' => $revive['skill']->id, 'hp' => $this->hp[$side]];
            }
        }

        return match (true) {
            $this->hp[1] === 0 && $this->hp[0] > 0 => 0,
            $this->hp[0] === 0 => 1,
            default => null,
        };
    }

    /**
     * Roll the side's first usable jutsu of the given kinds, in slot order;
     * pays chakra and counts the use on success. Using chakra wakes from
     * Death Mirage.
     *
     * @param  string|list<string>  $kinds
     * @param  (Closure(Skill): bool)|null  $only
     * @return array{skill: Skill, cost: int}|null
     */
    private function trigger(int $side, string|array $kinds, ?Closure $only = null): ?array
    {
        foreach ($this->fighters[$side]->skills as $skill) {
            $usesKey = $skill->usesGroup ?? $skill->id;

            if (! in_array($skill->kind, (array) $kinds, true)
                || ($only !== null && ! $only($skill))
                || ($skill->maxUses > 0 && ($this->uses[$side][$usesKey] ?? 0) >= $skill->maxUses)
                || ! $this->usable($side, $skill)) {
                continue;
            }

            $cost = $this->chakraCost($side, $skill);

            if ($this->mp[$side] < $cost || ! $this->chance($this->chanceOf($side, $skill))) {
                continue;
            }

            $this->mp[$side] -= $cost;
            $this->uses[$side][$usesKey] = ($this->uses[$side][$usesKey] ?? 0) + 1;

            if ($cost > 0) {
                $this->removeStatus($side, 'mirage', $skill->id);
            }

            return ['skill' => $skill, 'cost' => $cost];
        }

        return null;
    }

    /**
     * Chakra for one use; Eight Trigram Palm doubles it.
     */
    private function chakraCost(int $side, Skill $skill): int
    {
        $cost = (int) ceil($skill->chakra * $this->fighters[$side]->maxMp / config('game.combat.skill_chakra_divisor'));

        return $this->has($side, 'chakra_burn') ? $cost * 2 : $cost;
    }

    private function chanceOf(int $side, Skill $skill): int
    {
        $boost = (int) $this->statusAmount($side, 'bloodboil');

        if ($skill->kind !== 'revive') {
            return min(100, $skill->chance + $boost);
        }

        $combat = config('game.combat');

        return max($combat['revive_min_chance'], $skill->chance - $this->turn * $combat['revive_decay_per_turn']);
    }

    /**
     * The defender's dodge, after the attacker's drunkenness and the defender's snare.
     */
    private function dodgeAgainst(int $attacker, int $defender): int
    {
        return max(0, $this->fighters[$defender]->dodge
            + (int) $this->statusAmount($attacker, 'drunk')
            - (int) $this->statusAmount($defender, 'snare'));
    }

    /**
     * Mist-hide: attacks on its user miss more often as the fight goes on.
     */
    private function lostInMist(int $defender): bool
    {
        if (! $this->has($defender, 'mist')) {
            return false;
        }

        $mist = $this->statuses[$defender]['mist'];

        return $this->chance(min($mist['cap'], $this->turn * $mist['amount']));
    }

    /**
     * @return array{0: int, 1: array<string, mixed>} damage and notes for the log
     */
    private function damage(int $actor, int $target, ?Skill $skill, bool $crit, bool $parried): array
    {
        $attacker = $this->fighters[$actor];
        $defender = $this->fighters[$target];
        $notes = [];
        $damage = $this->random->getInt($attacker->minAttack, max($attacker->minAttack, $attacker->maxAttack));
        $damage = $damage * ($skill?->power ?? 100) / 100;

        if ($crit) {
            $damage = $damage * $attacker->critMultiplier / 100;
        }

        $scale = config('game.combat.defense_scale');
        $defense = max(0, $defender->defense - (int) $this->statusAmount($target, 'prison'));
        $damage *= 1 - $defense / ($defense + $scale);

        if ($parried) {
            $damage = $damage * self::PARRY_DAMAGE_PERCENT / 100;
        }

        // Some jutsu add a share of the target's max health, ignoring defense.
        $damage += $defender->maxHp * ($skill?->maxHpDamage ?? 0) / 100;
        $damage *= $this->damageFactor($actor, $target, $skill, $notes);
        [$dealt, $absorbed] = $this->absorb($target, max(1, (int) round($damage)));

        return [$dealt, [...$notes, ...($absorbed > 0 ? ['absorbed' => $absorbed] : [])]];
    }

    /**
     * Statuses that scale a hit: Cursed Seal, Dead Demon Seal, a frozen
     * target, Sunset and Static Field.
     *
     * @param  array<string, mixed>  $notes
     */
    private function damageFactor(int $actor, int $target, ?Skill $skill, array &$notes): float
    {
        $factor = 1.0;

        if ($this->has($actor, 'cursed_seal')) {
            $lost = 100 - $this->hp[$actor] * 100 / max(1, $this->fighters[$actor]->maxHp);
            $factor *= 1 + $lost * 0.5 / 100;
        }

        if ($this->has($actor, 'dead_demon')) {
            $seal = $this->statuses[$actor]['dead_demon'];
            $cut = $skill?->school === self::BODY_SCHOOL || ($skill?->lifesteal ?? 0) > 0 ? $seal['body'] : $seal['amount'];
            $factor *= (100 - $cut) / 100;
        }

        if ($this->has($target, 'freeze')) {
            $factor *= 2;
            $notes['shatter'] = true;
            $this->removeStatus($target, 'freeze');
        }

        if ($skill?->isElement() && $this->has($actor, 'sunset')) {
            $sunset = $this->statuses[$actor]['sunset'];

            if ($this->chance(min($sunset['cap'], $this->turn * $sunset['amount']))) {
                $factor *= 2;
                $notes['double'] = true;
            }
        }

        if ($skill?->school === 'lightning' && $this->has($target, 'shield')) {
            $factor *= 2 / 3;
        }

        return $factor;
    }

    private function backfireChance(): int
    {
        $combat = config('game.combat');

        return min($combat['bomb_backfire_cap'], $this->turn * $combat['bomb_backfire_per_turn']);
    }

    private function chance(int $percent): bool
    {
        return $percent > 0 && $this->random->getInt(1, 100) <= $percent;
    }

    private function leaderOnHealth(): int
    {
        $share = fn (int $side) => $this->hp[$side] / max(1, $this->fighters[$side]->maxHp);

        // The challenger has to do better than the defender to win a stalemate.
        return $share(0) > $share(1) ? 0 : 1;
    }

    /**
     * @return array{winner: int, events: list<array<string, mixed>>, hp: array{0: int, 1: int}, mp: array{0: int, 1: int}}
     */
    private function finish(int $winner, string $reason): array
    {
        $this->events[] = ['type' => 'end', 'winner' => $winner, 'reason' => $reason];

        return ['winner' => $winner, 'events' => $this->events, 'hp' => $this->hp, 'mp' => $this->mp];
    }
}

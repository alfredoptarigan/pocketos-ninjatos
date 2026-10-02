<?php

namespace App\Game;

use Random\Randomizer;

/**
 * Turn-based auto battle in the spirit of the original server: the two sides
 * alternate actions until one falls, and the client replays the log.
 *
 * Side 0 is the challenger, side 1 the defender. Jutsu (App\Game\Skill) hook in
 * before the action ('extra'), as the action ('strike'), after it ('follow_up'),
 * when attacked ('block', 'counter', 'reflect', 'heal') and on knock-out ('revive').
 * Without jutsu the random rolls happen in exactly the old order.
 */
final class BattleSimulator
{
    private const PARRY_DAMAGE_PERCENT = 50;

    /** @var array{0: Combatant, 1: Combatant} */
    private array $fighters;

    /** @var array{0: int, 1: int} */
    private array $hp;

    /** @var array{0: int, 1: int} */
    private array $mp;

    /** @var array{0: int, 1: int} turns each side still has to skip */
    private array $stun;

    /** @var array{0: array<string, int>, 1: array<string, int>} jutsu uses this battle */
    private array $uses;

    /** @var list<array<string, mixed>> */
    private array $events;

    private int $turn;

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
        $actor = $this->chance($defender->priority) ? 1 : 0;

        for ($this->turn = 0; $this->turn < config('game.combat.max_turns'); $this->turn++) {
            $winner = $this->takeTurn($actor, 1 - $actor);

            if ($winner !== null) {
                return $this->finish($winner, 'ko');
            }

            $actor = 1 - $actor;
        }

        return $this->finish($this->leaderOnHealth(), 'timeout');
    }

    /**
     * One side's turn; returns the winner if it ends the battle.
     */
    private function takeTurn(int $actor, int $target): ?int
    {
        if ($this->stun[$actor] > 0) {
            $this->stun[$actor]--;
            $this->events[] = ['type' => 'stunned', 'actor' => $actor];

            return null;
        }

        if ($extra = $this->trigger($actor, 'extra')) {
            $this->strike('extra', $actor, $target, $extra);

            if (($winner = $this->knockout()) !== null) {
                return $winner;
            }
        }

        $jutsu = $this->trigger($actor, 'strike');
        $landed = $this->strike('attack', $actor, $target, $jutsu);

        if (($winner = $this->knockout()) !== null) {
            return $winner;
        }

        if ($landed && $jutsu === null && ($followUp = $this->trigger($actor, 'follow_up'))) {
            $this->strike('follow_up', $actor, $target, $followUp);

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
     * Resolve one hit and the defender's reactions; returns whether it landed.
     *
     * @param  array{skill: Skill, cost: int}|null  $jutsu
     */
    private function strike(string $type, int $actor, int $target, ?array $jutsu): bool
    {
        $skill = $jutsu['skill'] ?? null;
        $attacker = $this->fighters[$actor];

        // A thrown-back bomb hits its thrower.
        $backfire = $skill?->backfire && $this->chance($this->backfireChance());
        if ($backfire) {
            $target = $actor;
        }

        $defender = $this->fighters[$target];
        $hit = ! $this->chance($defender->dodge);
        $blocked = $hit && ! $backfire && $this->trigger($target, 'block') !== null;
        $crit = $hit && ! $blocked && $this->chance($attacker->crit);
        $parried = $hit && ! $blocked && $this->chance($defender->parry);
        $damage = $hit && ! $blocked ? $this->damage($attacker, $defender, $skill, $crit, $parried) : 0;
        $this->hp[$target] = max(0, $this->hp[$target] - $damage);

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
        ];

        if ($skill) {
            $event = [...$event, ...$this->jutsuEffects($skill, $actor, $target, $damage, $backfire)];
            $event = [...$event, 'skill' => $skill->id, 'mpCost' => $jutsu['cost'], 'actorMp' => $this->mp[$actor]];
        }

        $this->events[] = $event;

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
    private function jutsuEffects(Skill $skill, int $actor, int $target, int $damage, bool $backfire): array
    {
        $effects = $backfire ? ['backfire' => true] : [];
        $maxHp = $this->fighters[$actor]->maxHp;

        if ($skill->lifesteal > 0 && $damage > 0) {
            $this->hp[$actor] = min($maxHp, $this->hp[$actor] + (int) round($damage * $skill->lifesteal / 100));
            $effects['actorHp'] = $this->hp[$actor];
        }

        if ($skill->selfDamage > 0 && $damage > 0) {
            $this->hp[$actor] = max(0, $this->hp[$actor] - (int) round($damage * $skill->selfDamage / 100));
            $effects['actorHp'] = $this->hp[$actor];
        }

        if ($skill->stun > 0 && $damage > 0 && ! $backfire) {
            $this->stun[$target] += $skill->stun;
        }

        if ($skill->selfStun > 0) {
            $this->stun[$actor] += $skill->selfStun;
        }

        return $effects;
    }

    /**
     * Jutsu the defender uses after taking damage: reflect, then heal.
     */
    private function react(int $defender, int $attacker, int $damage): void
    {
        if ($this->hp[$defender] === 0) {
            return;
        }

        if ($reflect = $this->trigger($defender, 'reflect')) {
            $returned = max(1, (int) round($damage * $reflect['skill']->power / 100));
            $this->hp[$attacker] = max(0, $this->hp[$attacker] - $returned);
            $this->events[] = [
                'type' => 'reflect', 'actor' => $defender, 'target' => $attacker,
                'skill' => $reflect['skill']->id, 'damage' => $returned, 'targetHp' => $this->hp[$attacker],
            ];
        }

        if ($heal = $this->trigger($defender, 'heal')) {
            $lost = $this->fighters[$defender]->maxHp - $this->hp[$defender];
            $amount = (int) round($lost * $heal['skill']->power / 100);
            $this->hp[$defender] += $amount;
            $this->events[] = ['type' => 'heal', 'actor' => $defender, 'skill' => $heal['skill']->id, 'amount' => $amount, 'hp' => $this->hp[$defender]];
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
     * Roll the side's jutsu of one kind; pays chakra and counts the use on success.
     *
     * @return array{skill: Skill, cost: int}|null
     */
    private function trigger(int $side, string $kind): ?array
    {
        $fighter = $this->fighters[$side];

        foreach ($fighter->skills as $skill) {
            if ($skill->kind !== $kind || ($skill->maxUses > 0 && ($this->uses[$side][$skill->id] ?? 0) >= $skill->maxUses)) {
                continue;
            }

            $cost = (int) ceil($skill->chakra * $fighter->maxMp / config('game.combat.skill_chakra_divisor'));

            if ($this->mp[$side] < $cost || ! $this->chance($this->chanceOf($skill))) {
                continue;
            }

            $this->mp[$side] -= $cost;
            $this->uses[$side][$skill->id] = ($this->uses[$side][$skill->id] ?? 0) + 1;

            return ['skill' => $skill, 'cost' => $cost];
        }

        return null;
    }

    private function chanceOf(Skill $skill): int
    {
        if ($skill->kind !== 'revive') {
            return $skill->chance;
        }

        $combat = config('game.combat');

        return max($combat['revive_min_chance'], $skill->chance - $this->turn * $combat['revive_decay_per_turn']);
    }

    private function backfireChance(): int
    {
        $combat = config('game.combat');

        return min($combat['bomb_backfire_cap'], $this->turn * $combat['bomb_backfire_per_turn']);
    }

    private function damage(Combatant $attacker, Combatant $defender, ?Skill $skill, bool $crit, bool $parried): int
    {
        $damage = $this->random->getInt($attacker->minAttack, max($attacker->minAttack, $attacker->maxAttack));
        $damage = $damage * ($skill?->power ?? 100) / 100;

        if ($crit) {
            $damage = $damage * $attacker->critMultiplier / 100;
        }

        $scale = config('game.combat.defense_scale');
        $damage *= 1 - $defender->defense / ($defender->defense + $scale);

        if ($parried) {
            $damage = $damage * self::PARRY_DAMAGE_PERCENT / 100;
        }

        // Some jutsu add a share of the target's max health, ignoring defense.
        $damage += $defender->maxHp * ($skill?->maxHpDamage ?? 0) / 100;

        return max(1, (int) round($damage));
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

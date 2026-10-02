<?php

namespace App\Game;

use Random\Randomizer;

/**
 * Turn-based auto battle in the spirit of the original server: the two sides
 * alternate normal attacks until one falls, and the client replays the log.
 *
 * Side 0 is the challenger, side 1 the defender.
 */
final class BattleSimulator
{
    private const PARRY_DAMAGE_PERCENT = 50;

    public function __construct(private readonly Randomizer $random) {}

    /**
     * @return array{winner: int, events: list<array<string, mixed>>, hp: array{0: int, 1: int}}
     */
    public function simulate(Combatant $challenger, Combatant $defender): array
    {
        $fighters = [$challenger, $defender];
        $hp = [$challenger->hp, $defender->hp];
        $events = [];
        $actor = $this->chance($defender->priority) ? 1 : 0;

        for ($turn = 0; $turn < config('game.combat.max_turns'); $turn++) {
            $target = 1 - $actor;
            $events[] = $this->strike('attack', $actor, $target, $fighters, $hp);

            if ($hp[$target] === 0) {
                return $this->finish($events, $hp, $actor, 'ko');
            }

            $wasHit = end($events)['hit'];
            if ($wasHit && $this->chance($fighters[$target]->counter)) {
                $events[] = $this->strike('counter', $target, $actor, $fighters, $hp);

                if ($hp[$actor] === 0) {
                    return $this->finish($events, $hp, $target, 'ko');
                }
            }

            $actor = $target;
        }

        return $this->finish($events, $hp, $this->leaderOnHealth($fighters, $hp), 'timeout');
    }

    /**
     * @param  array{0: Combatant, 1: Combatant}  $fighters
     * @param  array{0: int, 1: int}  $hp  updated in place for the running battle
     * @return array<string, mixed>
     */
    private function strike(string $type, int $actor, int $target, array $fighters, array &$hp): array
    {
        $attacker = $fighters[$actor];
        $defender = $fighters[$target];
        $hit = ! $this->chance($defender->dodge);
        $crit = $hit && $this->chance($attacker->crit);
        $parried = $hit && $this->chance($defender->parry);
        $damage = $hit ? $this->damage($attacker, $defender, $crit, $parried) : 0;
        $hp[$target] = max(0, $hp[$target] - $damage);

        return [
            'type' => $type,
            'actor' => $actor,
            'target' => $target,
            'hit' => $hit,
            'crit' => $crit,
            'parried' => $parried,
            'damage' => $damage,
            'targetHp' => $hp[$target],
        ];
    }

    private function damage(Combatant $attacker, Combatant $defender, bool $crit, bool $parried): int
    {
        $damage = $this->random->getInt($attacker->minAttack, max($attacker->minAttack, $attacker->maxAttack));

        if ($crit) {
            $damage = $damage * $attacker->critMultiplier / 100;
        }

        $scale = config('game.combat.defense_scale');
        $damage *= 1 - $defender->defense / ($defender->defense + $scale);

        if ($parried) {
            $damage = $damage * self::PARRY_DAMAGE_PERCENT / 100;
        }

        return max(1, (int) round($damage));
    }

    private function chance(int $percent): bool
    {
        return $percent > 0 && $this->random->getInt(1, 100) <= $percent;
    }

    /**
     * @param  array{0: Combatant, 1: Combatant}  $fighters
     * @param  array{0: int, 1: int}  $hp
     */
    private function leaderOnHealth(array $fighters, array $hp): int
    {
        $share = fn (int $side) => $hp[$side] / max(1, $fighters[$side]->maxHp);

        // The challenger has to do better than the defender to win a stalemate.
        return $share(0) > $share(1) ? 0 : 1;
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @param  array{0: int, 1: int}  $hp
     * @return array{winner: int, events: list<array<string, mixed>>, hp: array{0: int, 1: int}}
     */
    private function finish(array $events, array $hp, int $winner, string $reason): array
    {
        $events[] = ['type' => 'end', 'winner' => $winner, 'reason' => $reason];

        return ['winner' => $winner, 'events' => $events, 'hp' => $hp];
    }
}

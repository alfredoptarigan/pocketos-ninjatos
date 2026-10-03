<?php

namespace App\Game\Concerns;

use App\Game\Skill;

/**
 * What jutsu do besides damage (config('skills.skills') 'effect'): battle-long
 * jutsu at the start, effect-only casts, effects of landed hits, and the
 * defender's reactions to damage. Used by App\Game\BattleSimulator.
 */
trait AppliesJutsuEffects
{
    private const MAX_GATES = 8;

    /** @var array{0: array{open: int, last: int}, 1: array{open: int, last: int}} Eight Inner Gates per side */
    private array $gates;

    /** @var array{0: bool, 1: bool} poison works once a fight */
    private array $poisoned;

    /**
     * Battle-long jutsu ('battle' kind) switch on before the first turn, if
     * their chakra can be paid.
     */
    private function startBattle(): void
    {
        foreach ([0, 1] as $side) {
            foreach ($this->fighters[$side]->skills as $skill) {
                $cost = $this->chakraCost($side, $skill);

                if ($skill->kind !== 'battle' || $this->mp[$side] < $cost) {
                    continue;
                }

                $this->mp[$side] -= $cost;
                $skill->effect === 'drunk'
                    ? $this->applyStatus(1 - $side, 'drunk', null, $skill->param('amount'), $skill->id, $side, ['crit' => $skill->param('crit')])
                    : $this->applyStatus($side, $skill->effect, null, $skill->param('amount'), $skill->id, $side, ['cap' => $skill->param('cap')]);
            }
        }
    }

    /**
     * An effect-only jutsu (power 0): logged as a cast, then its effect.
     *
     * @param  array{skill: Skill, cost: int}  $jutsu
     */
    private function cast(int $actor, int $target, array $jutsu): void
    {
        $index = count($this->events);
        $this->events[] = [
            'type' => 'cast', 'actor' => $actor, 'target' => $target,
            'skill' => $jutsu['skill']->id, 'mpCost' => $jutsu['cost'], 'actorMp' => $this->mp[$actor],
        ];
        $this->events[$index] = [...$this->events[$index], ...$this->applyEffect($jutsu['skill'], $actor, $target, 0)];
    }

    /**
     * Whether a jutsu can be used right now, before its chance is rolled.
     */
    private function usable(int $side, Skill $skill): bool
    {
        if (($skill->isElement() && $this->has($side, 'seal')) || ($skill->school === 'fire' && $this->has($side, 'freeze'))) {
            return false;
        }

        // An effect-only jutsu is not cast again while its effect still lasts.
        if ($skill->power === 0 && $this->effectLasts($side, $skill)) {
            return false;
        }

        return match ($skill->effect) {
            'charm' => $this->hasBuff(1 - $side),
            'cleanse' => $this->removableDebuffs($side) !== [],
            'waterfall' => $this->hasDebuff($side),
            'shield' => ! $this->has($side, 'shield'),
            'gates' => $this->gates[$side]['open'] < self::MAX_GATES && $this->turn - $this->gates[$side]['last'] >= $skill->param('cooldown', 1),
            default => $skill->kind !== 'revive' || ! $this->immobile($side),
        };
    }

    /**
     * Whether the status a jutsu would put on its user or target is still on.
     */
    private function effectLasts(int $side, Skill $skill): bool
    {
        $target = 1 - $side;

        return match ($skill->effect) {
            'bloodboil', 'invulnerable', 'regen' => $this->has($side, $skill->effect),
            'freeze', 'charm', 'slow', 'mirage', 'chakra_burn', 'snare', 'clay', 'seal', 'dead_demon' => $this->has($target, $skill->effect),
            'waterfall' => $this->stun[$target] > 0,
            'poison' => $this->poisoned[$target],
            default => false,
        };
    }

    /**
     * Apply a jutsu's effect; $damage is what its hit dealt (0 for casts).
     *
     * @return array<string, int> fields to add to the logged event
     */
    private function applyEffect(Skill $skill, int $actor, int $target, int $damage): array
    {
        $id = $skill->id;
        $turns = $skill->param('turns') ?: null;
        $amount = $skill->param('amount');

        switch ($skill->effect) {
            case 'burn':
                if ($damage > 0 && ! $this->has(0, 'mist') && ! $this->has(1, 'mist')) {
                    $total = $damage * $amount / 100 * ($this->has($target, 'drunk') ? 1.5 : 1);
                    $this->applyStatus($target, 'burn', $turns, max(1, (int) round($total / $turns)), $id, $actor);
                }
                break;
            case 'bloodboil':
                $this->applyStatus($actor, 'bloodboil', $turns, $amount, $id, $actor, ['recoil' => $skill->param('recoil')]);
                break;
            case 'waterfall':
                $this->stun[$target] += $turns;
                break;
            case 'freeze':
            case 'charm':
            case 'slow':
            case 'mirage':
            case 'chakra_burn':
                $this->applyStatus($target, $skill->effect, $turns, $amount, $id, $actor);
                break;
            case 'snare':
                $this->applyStatus($target, 'snare', 1, $amount, $id, $actor);
                break;
            case 'clay':
                $this->applyStatus($target, 'clay', $turns, 0, $id, $actor, ['stored' => 0]);
                break;
            case 'seal':
            case 'dead_demon':
                // Cursed Seal of Heaven halves sealing on its user.
                $sealed = $this->has($target, 'cursed_seal') ? max(1, intdiv($turns, 2)) : $turns;
                $this->applyStatus($target, $skill->effect, $sealed, $amount, $id, $actor, ['body' => $skill->param('body')]);
                break;
            case 'invulnerable':
                $this->applyStatus($actor, 'invulnerable', $turns, 0, $id, $actor);
                break;
            case 'regen':
                $this->removeStatus($actor, 'poison', $id);
                $this->applyStatus($actor, 'regen', $turns, $amount, $id, $actor);
                break;
            case 'shield':
                $points = (int) round($this->fighters[$actor]->maxHp * $amount / 100);
                $this->applyStatus($actor, 'shield', null, $points, $id, $actor, ['max' => $points]);
                break;
            case 'gates':
                $this->gates[$actor] = ['open' => $this->gates[$actor]['open'] + 1, 'last' => $this->turn];
                $this->applyStatus($actor, 'gates', null, $this->gates[$actor]['open'] * $amount, $id, $actor);
                break;
            case 'poison':
                if (! $this->poisoned[$target]) {
                    $this->poisoned[$target] = true;
                    $this->applyStatus($target, 'poison', $turns, $amount, $id, $actor);
                }
                break;
            case 'prison':
                return $this->earthPrison($skill, $actor, $target);
            case 'chakra_cut':
                $this->mp[$target] = max(0, $this->mp[$target] - (int) round($this->fighters[$target]->maxMp * $amount / 100));

                return ['targetMp' => $this->mp[$target]];
            case 'purge':
                foreach (['burn', 'poison', 'drunk'] as $status) {
                    $this->removeStatus($actor, $status, $id);
                }
                break;
            case 'cleanse':
                $debuffs = $this->removableDebuffs($actor);
                $this->removeStatus($actor, $debuffs[$this->random->getInt(0, count($debuffs) - 1)], $id);
                break;
        }

        return [];
    }

    /**
     * Earth Prison: drains chakra to the user and lowers the target's defense for good.
     *
     * @return array{targetMp: int, actorMp: int}
     */
    private function earthPrison(Skill $skill, int $actor, int $target): array
    {
        $drain = min($this->mp[$target], (int) round($this->fighters[$target]->maxMp * $skill->param('amount') / 100));
        $this->mp[$target] -= $drain;
        $this->mp[$actor] = min($this->fighters[$actor]->maxMp, $this->mp[$actor] + $drain);
        $this->applyStatus($target, 'prison', null, $this->statusAmount($target, 'prison') + $skill->param('defense'), $skill->id, $actor);

        return ['targetMp' => $this->mp[$target], 'actorMp' => $this->mp[$actor]];
    }

    /**
     * Jutsu the defender uses after taking damage: reflect, then one healing
     * jutsu, then the Eight Inner Gates.
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

        $heal = $this->trigger($defender, ['heal', 'hurt'], fn (Skill $skill) => $skill->effect !== 'gates');

        if ($heal !== null && $heal['skill']->kind === 'heal') {
            $this->removeStatus($defender, 'poison', $heal['skill']->id);
            $lost = $this->fighters[$defender]->maxHp - $this->hp[$defender];
            $amount = $this->healed($defender, (int) round($lost * $heal['skill']->power / 100));
            $this->hp[$defender] += $amount;
            $this->events[] = ['type' => 'heal', 'actor' => $defender, 'skill' => $heal['skill']->id, 'amount' => $amount, 'hp' => $this->hp[$defender]];
        } elseif ($heal !== null) {
            $this->cast($defender, $attacker, $heal);
        }

        if ($gate = $this->trigger($defender, 'hurt', fn (Skill $skill) => $skill->effect === 'gates')) {
            $this->cast($defender, $attacker, $gate);
        }
    }
}

<?php

namespace App\Game\Concerns;

/**
 * Battle statuses (buffs and debuffs from config('skills.skills') effects):
 * applying, ticking at the start of the holder's turn, counting down at its
 * end, and the turns they cost. Used by App\Game\BattleSimulator.
 */
trait TracksStatuses
{
    private const BUFFS = ['shield', 'invulnerable', 'bloodboil', 'regen', 'gates', 'sunset', 'mist', 'cloud', 'cursed_seal'];

    private const DEBUFFS = ['burn', 'drunk', 'freeze', 'slow', 'poison', 'seal', 'dead_demon', 'charm', 'snare', 'clay', 'prison', 'mirage', 'chakra_burn'];

    // Debuffs that stop moving: Tailed Beast Heart cannot lift them, revives fail under them.
    private const IMMOBILE = ['freeze', 'charm'];

    /** @var array{0: array<string, array<string, mixed>>, 1: array<string, array<string, mixed>>} status => turns, amount, skill, ... */
    private array $statuses;

    private function has(int $side, string $status): bool
    {
        return isset($this->statuses[$side][$status]);
    }

    private function statusAmount(int $side, string $status): int|float
    {
        return $this->statuses[$side][$status]['amount'] ?? 0;
    }

    /**
     * Put a status on a side (null turns: for the rest of the fight). A new
     * burn adds its turns to the old one; other statuses are replaced.
     *
     * @param  array<string, mixed>  $extra
     */
    private function applyStatus(int $side, string $status, ?int $turns, int|float $amount, ?string $skill, int $source, array $extra = []): void
    {
        $old = $this->statuses[$side][$status] ?? null;

        if ($status === 'burn' && $old !== null) {
            $turns += $old['turns'];
            $amount = max($amount, $old['amount']);
        }

        $this->statuses[$side][$status] = ['turns' => $turns, 'amount' => $amount, 'skill' => $skill, 'source' => $source, ...$extra];
        $this->events[] = ['type' => 'status', 'actor' => $side, 'status' => $status, 'turns' => $turns, 'skill' => $skill, 'source' => $source];
    }

    private function removeStatus(int $side, string $status, ?string $skill = null): void
    {
        if (! $this->has($side, $status)) {
            return;
        }

        unset($this->statuses[$side][$status]);
        $this->events[] = ['type' => 'expire', 'actor' => $side, 'status' => $status, ...($skill !== null ? ['skill' => $skill] : [])];
    }

    private function hasDebuff(int $side): bool
    {
        return $this->stun[$side] > 0 || array_intersect(self::DEBUFFS, array_keys($this->statuses[$side])) !== [];
    }

    private function hasBuff(int $side): bool
    {
        return array_intersect(self::BUFFS, array_keys($this->statuses[$side])) !== [];
    }

    /**
     * Debuffs Tailed Beast Heart can lift.
     *
     * @return list<string>
     */
    private function removableDebuffs(int $side): array
    {
        return array_values(array_diff(array_intersect(self::DEBUFFS, array_keys($this->statuses[$side])), self::IMMOBILE));
    }

    private function immobile(int $side): bool
    {
        return $this->stun[$side] > 0 || array_intersect(self::IMMOBILE, array_keys($this->statuses[$side])) !== [];
    }

    /**
     * Burn, poison, nightmares and healing over time, before the side moves.
     */
    private function tickStatuses(int $side): void
    {
        $fighter = $this->fighters[$side];

        foreach (['burn', 'poison', 'mirage', 'regen'] as $status) {
            if (! $this->has($side, $status) || $this->hp[$side] === 0) {
                continue;
            }

            $amount = $this->statusAmount($side, $status);
            $event = ['type' => 'tick', 'actor' => $side, 'status' => $status];

            if ($status === 'regen') {
                $heal = $this->healed($side, (int) round($fighter->maxHp * $amount / 100));
                $this->hp[$side] = min($fighter->maxHp, $this->hp[$side] + $heal);
                $this->events[] = [...$event, 'heal' => $heal, 'hp' => $this->hp[$side]];

                continue;
            }

            $damage = $status === 'burn' ? (int) $amount : (int) round($fighter->maxHp * $amount / 100);
            $this->hp[$side] = max(0, $this->hp[$side] - $damage);

            if ($status === 'mirage') {
                $this->mp[$side] = max(0, $this->mp[$side] - (int) round($fighter->maxMp * $amount / 100));
                $event['mp'] = $this->mp[$side];
            }

            $this->events[] = [...$event, 'damage' => $damage, 'hp' => $this->hp[$side]];
        }
    }

    /**
     * Count down the side's statuses after its turn; clay explodes when it runs out.
     */
    private function countDownStatuses(int $side): void
    {
        foreach ($this->statuses[$side] as $status => $data) {
            if ($data['turns'] === null) {
                continue;
            }

            $this->statuses[$side][$status]['turns']--;

            if ($this->statuses[$side][$status]['turns'] > 0) {
                continue;
            }

            if ($status === 'clay' && $this->hp[$side] > 0) {
                $damage = $data['stored'];
                $this->hp[$side] = max(0, $this->hp[$side] - $damage);
                $this->events[] = ['type' => 'tick', 'actor' => $side, 'status' => 'clay', 'damage' => $damage, 'hp' => $this->hp[$side]];
            }

            $this->removeStatus($side, $status);
        }
    }

    /**
     * Whether the side loses this turn: stunned, frozen, charmed, or too slow.
     */
    private function losesTurn(int $side): bool
    {
        if ($this->stun[$side] > 0) {
            $this->stun[$side]--;
            $this->events[] = ['type' => 'stunned', 'actor' => $side];

            return true;
        }

        foreach ([...self::IMMOBILE, 'slow'] as $status) {
            if (! $this->has($side, $status)) {
                continue;
            }

            $tooSlow = $status !== 'slow'
                || $this->chance((int) round($this->statusAmount($side, 'slow') * config('skills.speed_chance_percent') / 100));

            if ($tooSlow) {
                $this->events[] = ['type' => 'stunned', 'actor' => $side, 'reason' => $status];

                return true;
            }
        }

        return false;
    }

    /**
     * Whether the side acts again thanks to its open gates (never twice in a row).
     */
    private function hasted(int $side): bool
    {
        if (! $this->has($side, 'gates') || $this->lastHaste === $this->turn - 1) {
            return false;
        }

        if (! $this->chance((int) round($this->statusAmount($side, 'gates') * config('skills.speed_chance_percent') / 100))) {
            return false;
        }

        $this->lastHaste = $this->turn;
        $this->events[] = ['type' => 'haste', 'actor' => $side];

        return true;
    }

    /**
     * Flying Thunder God: after the owner's turn the cloud strikes a random side.
     */
    private function thunderCloud(int $owner): void
    {
        if (! $this->has($owner, 'cloud')) {
            return;
        }

        $side = $this->random->getInt(0, 1);
        [$damage, $absorbed] = $this->absorb($side, (int) round($this->fighters[$side]->maxHp * $this->statusAmount($owner, 'cloud') / 100));
        $this->hp[$side] = max(0, $this->hp[$side] - $damage);
        $this->events[] = ['type' => 'cloud', 'actor' => $owner, 'target' => $side, 'damage' => $damage, 'absorbed' => $absorbed, 'targetHp' => $this->hp[$side]];
    }

    /**
     * Static Field soaks up damage first.
     *
     * @return array{0: int, 1: int} damage left and damage absorbed
     */
    private function absorb(int $side, int $damage): array
    {
        if (! $this->has($side, 'shield')) {
            return [$damage, 0];
        }

        $absorbed = min($damage, (int) $this->statusAmount($side, 'shield'));
        $this->statuses[$side]['shield']['amount'] -= $absorbed;

        if ($this->statuses[$side]['shield']['amount'] <= 0) {
            $this->removeStatus($side, 'shield');
        }

        return [$damage - $absorbed, $absorbed];
    }

    /**
     * Healing after Dead Demon Seal's cut.
     */
    private function healed(int $side, int $amount): int
    {
        return $this->has($side, 'dead_demon')
            ? (int) round($amount * (100 - $this->statusAmount($side, 'dead_demon')) / 100)
            : $amount;
    }
}

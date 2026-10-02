<?php

namespace App\Game;

/**
 * One side of a battle. Percent stats are 0-100; critMultiplier is a
 * percentage of normal damage (200 = double).
 */
final readonly class Combatant
{
    public function __construct(
        public string $name,
        public int $hp,
        public int $maxHp,
        public int $minAttack,
        public int $maxAttack,
        public int $defense,
        public int $dodge,
        public int $crit,
        public int $critMultiplier,
        public int $parry,
        public int $counter,
        public int $priority,
    ) {}

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}

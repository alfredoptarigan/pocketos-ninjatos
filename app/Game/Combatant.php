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
        public int $mp = 0,
        public int $maxMp = 0,
        /** @var list<Skill> */
        public array $skills = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        // The replay only needs which jutsu were learned, not their rules.
        return [...get_object_vars($this), 'skills' => array_map(fn (Skill $skill) => $skill->id, $this->skills)];
    }
}

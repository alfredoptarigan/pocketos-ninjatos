<?php

namespace App\Game;

/**
 * Stats added on top of level, avatar and gear: from the avatar collection
 * and the worn title. Attributes convert with config('game.attributes').
 */
final readonly class StatBonus
{
    public function __construct(
        public int $strength = 0,
        public int $agility = 0,
        public int $stamina = 0,
        public int $hp = 0,
        public int $attack = 0,
        public int $defense = 0,
        public int $dodge = 0,
        public int $crit = 0,
        public float $hpPercent = 0,
        public float $attackPercent = 0,
        public float $defensePercent = 0,
    ) {}

    public function plus(self $other): self
    {
        $sum = [];
        foreach (get_object_vars($this) as $name => $value) {
            $sum[$name] = $value + $other->{$name};
        }

        return new self(...$sum);
    }

    /**
     * Flat health, attack and dodge including the converted attributes.
     *
     * @return array{hp: int, attack: int, dodge: int}
     */
    public function flat(): array
    {
        $rates = config('game.attributes');

        return [
            'hp' => $this->hp + $this->stamina * $rates['stamina_hp'],
            'attack' => $this->attack + $this->strength * $rates['strength_attack'],
            'dodge' => $this->dodge + intdiv($this->agility, $rates['agility_per_dodge']),
        ];
    }
}

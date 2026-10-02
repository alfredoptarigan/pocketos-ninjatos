<?php

namespace App\Game;

use App\Models\Character;

/**
 * A character's battle stats, derived from level and avatar aptitudes.
 */
final readonly class CombatStats
{
    public function __construct(
        public int $maxHp,
        public int $maxMp,
        public int $minAttack,
        public int $maxAttack,
        public int $defense,
        public int $dodge,
        public int $crit,
        public int $critMultiplier,
        public int $parry,
    ) {}

    public static function for(Character $character): self
    {
        $combat = config('game.combat');
        $growth = config("game.avatars.{$character->avatar}");
        $levels = ($character->level ?? 1) - 1;
        $minAttack = $combat['base_min_attack'] + (int) round($levels * $growth['attack']);

        return new self(
            maxHp: $combat['base_hp'] + $levels * $growth['hp'],
            maxMp: $combat['base_mp'] + $levels * $combat['mp_per_level'],
            minAttack: $minAttack,
            maxAttack: (int) round($minAttack * $combat['max_attack_percent'] / 100),
            defense: (int) round($levels * $growth['defense']),
            dodge: $growth['dodge'],
            crit: $combat['crit'],
            critMultiplier: $combat['crit_multiplier'],
            parry: $combat['parry'],
        );
    }
}

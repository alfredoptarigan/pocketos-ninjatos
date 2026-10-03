<?php

namespace App\Game;

use App\Models\Character;

/**
 * A character's battle stats, derived from level, avatar aptitudes, worn gear and outfit.
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

    /**
     * Level and avatar growth, plus whatever gear the ninja wears.
     */
    public static function for(Character $character): self
    {
        $combat = config('game.combat');
        $growth = config("game.avatars.{$character->avatar}");
        $levels = ($character->level ?? 1) - 1;
        $minAttack = $combat['base_min_attack'] + (int) round($levels * $growth['attack']);
        $gear = $character->exists
            ? $character->loadMissing('wornGear.equipment')->wornGear->pluck('equipment')
            : collect();
        // A worn outfit raises health, attack and defense by its rarity's percentage.
        $percent = 100 + ($character->outfit?->bonusPercent() ?? 0);
        $boost = fn (int $value) => (int) round($value * $percent / 100);

        return new self(
            maxHp: $boost($combat['base_hp'] + $levels * $growth['hp'] + $gear->sum('max_hp')),
            maxMp: $combat['base_mp'] + $levels * $combat['mp_per_level'],
            minAttack: $boost($minAttack + $gear->sum('min_attack')),
            maxAttack: $boost((int) round($minAttack * $combat['max_attack_percent'] / 100) + $gear->sum('max_attack')),
            defense: $boost((int) round($levels * $growth['defense']) + $gear->sum('defense')),
            dodge: $growth['dodge'],
            crit: $combat['crit'] + $gear->sum('crit'),
            critMultiplier: $combat['crit_multiplier'],
            parry: $combat['parry'],
        );
    }
}

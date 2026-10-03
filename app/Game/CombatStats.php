<?php

namespace App\Game;

use App\Models\Character;

/**
 * A character's battle stats, derived from level, avatar aptitudes, worn gear,
 * outfit and the bonuses of Character::statBonus().
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
        $bonus = $character->exists ? $character->statBonus() : new StatBonus;
        $flat = $bonus->flat();
        // A worn outfit raises health, attack and defense by its rarity's percentage.
        $outfit = $character->outfit?->bonusPercent($character->outfitLevel($character->outfit_id)) ?? 0;
        $boost = fn (int $value, float $percent) => (int) round($value * (100 + $outfit + $percent) / 100);

        return new self(
            maxHp: $boost($combat['base_hp'] + $levels * $growth['hp'] + $gear->sum('max_hp') + $flat['hp'], $bonus->hpPercent),
            maxMp: $combat['base_mp'] + $levels * $combat['mp_per_level'],
            minAttack: $boost($minAttack + $gear->sum('min_attack') + $flat['attack'], $bonus->attackPercent),
            maxAttack: $boost((int) round($minAttack * $combat['max_attack_percent'] / 100) + $gear->sum('max_attack') + $flat['attack'], $bonus->attackPercent),
            defense: $boost((int) round($levels * $growth['defense']) + $gear->sum('defense') + $bonus->defense, $bonus->defensePercent),
            dodge: $growth['dodge'] + $flat['dodge'],
            crit: $combat['crit'] + $gear->sum('crit') + $bonus->crit,
            critMultiplier: $combat['crit_multiplier'],
            parry: $combat['parry'],
        );
    }
}

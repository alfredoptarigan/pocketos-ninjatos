<?php

namespace App\Game;

final class Leveling
{
    public static function expToNext(int $level): int
    {
        $combat = config('game.combat');

        return (int) round($combat['exp_base'] * $level ** $combat['exp_exponent']);
    }

    /**
     * Add experience and return the resulting [level, exp into that level].
     *
     * @return array{0: int, 1: int}
     */
    public static function gain(int $level, int $exp, int $gained): array
    {
        $maxLevel = config('game.combat.max_level');
        $exp += $gained;

        while ($level < $maxLevel && $exp >= self::expToNext($level)) {
            $exp -= self::expToNext($level);
            $level++;
        }

        return $level >= $maxLevel ? [$maxLevel, 0] : [$level, $exp];
    }
}

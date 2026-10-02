<?php

namespace App\Game;

use Carbon\CarbonInterface;

final class Vitals
{
    /**
     * Health or chakra after resting since `since`, capped at `max`.
     */
    public static function recovered(int $current, int $max, ?CarbonInterface $since, CarbonInterface $now): int
    {
        if ($since === null || $current >= $max) {
            return min($current, $max);
        }

        $minutes = (int) floor($since->diffInSeconds($now) / 60);
        $perMinute = $max * config('game.combat.regen_percent_per_minute') / 100;

        return min($max, $current + (int) floor($minutes * $perMinute));
    }
}

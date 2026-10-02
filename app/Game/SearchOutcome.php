<?php

namespace App\Game;

/**
 * What searching a spot turns up. Rates are per 10,000 searches, as in the
 * original roleoutsearch table; whatever they leave over finds nothing.
 */
final class SearchOutcome
{
    public const ROLLS = 10000;

    /**
     * @param  array<string, int>  $rates  outcome => chance per 10,000
     * @param  int  $roll  1..ROLLS
     */
    public static function pick(array $rates, int $roll): string
    {
        foreach ($rates as $outcome => $rate) {
            if ($roll <= $rate) {
                return $outcome;
            }
            $roll -= $rate;
        }

        return 'nothing';
    }
}

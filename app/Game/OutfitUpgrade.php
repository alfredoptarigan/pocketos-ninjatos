<?php

namespace App\Game;

/**
 * What raising an outfit to the next +N costs (config('game.outfits.upgrade')).
 */
final readonly class OutfitUpgrade
{
    public function __construct(
        public int $toLevel,
        public int $gold,
        public int $shards,
        public int $characterLevel,
    ) {}

    /**
     * The upgrade from $level to $level + 1; null once at the maximum.
     */
    public static function from(int $level): ?self
    {
        $rules = config('game.outfits.upgrade');
        $next = $level + 1;

        if ($next > $rules['max_level']) {
            return null;
        }

        return new self(
            toLevel: $next,
            gold: $next * $rules['gold'],
            shards: $next * $rules['shards'],
            characterLevel: max(1, $rules['level_step'] * ($next - 1)),
        );
    }
}

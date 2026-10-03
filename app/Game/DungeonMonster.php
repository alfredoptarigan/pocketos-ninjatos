<?php

namespace App\Game;

use App\Models\Concerns\FightsAsOpponent;
use Illuminate\Support\Arr;

/**
 * A wave's leader in a dungeon, read from the dungeon's stages JSON.
 */
final class DungeonMonster
{
    use FightsAsOpponent;

    private const FIELDS = [
        'name', 'is_boss', 'level', 'max_hp', 'max_mp', 'min_atk', 'max_atk', 'defense',
        'crit', 'crit_multiplier', 'dodge', 'parry', 'counter', 'priority', 'exp', 'art',
    ];

    /**
     * @param  array{type: string, face: string, motions?: string, portrait?: string, background?: string}  $art
     */
    public function __construct(
        public readonly string $name,
        public readonly bool $is_boss,
        public readonly int $level,
        public readonly int $max_hp,
        public readonly int $max_mp,
        public readonly int $min_atk,
        public readonly int $max_atk,
        public readonly int $defense,
        public readonly int $crit,
        public readonly int $crit_multiplier,
        public readonly int $dodge,
        public readonly int $parry,
        public readonly int $counter,
        public readonly int $priority,
        public readonly int $exp,
        public readonly array $art,
    ) {}

    /**
     * @param  array<string, mixed>  $wave
     */
    public static function fromWave(array $wave): self
    {
        return new self(...Arr::only($wave, self::FIELDS));
    }
}

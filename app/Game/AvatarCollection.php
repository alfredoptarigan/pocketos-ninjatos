<?php

namespace App\Game;

use App\Models\Character;
use App\Models\Outfit;
use Illuminate\Support\Collection;

/**
 * A ninja's recorded outfits: their attributes and the tier reached per rarity.
 */
final readonly class AvatarCollection
{
    /**
     * @param  array<string, array{recorded: int, tier: int, percent: float}>  $tiers
     */
    public function __construct(public array $tiers, public StatBonus $bonus) {}

    public static function for(Character $character): self
    {
        /** @var Collection<int, Outfit> $recorded */
        $recorded = $character->outfits()->wherePivotNotNull('recorded_at')->whereNotNull('collection')->get();
        $tiers = [];
        $percent = 0.0;

        foreach (config('game.collection.tiers') as $rarity => $levels) {
            $count = $recorded->where('rarity', $rarity)->count();
            $reached = array_filter($levels, fn (array $tier) => $count >= $tier[0] && $character->level >= $tier[1]);
            $best = $reached === [] ? null : end($reached);
            $tiers[$rarity] = [
                'recorded' => $count,
                'tier' => count($reached),
                'percent' => $best[2] ?? 0,
            ];
            $percent += $best[2] ?? 0;
        }

        return new self($tiers, new StatBonus(
            strength: $recorded->sum('collection.strength'),
            agility: $recorded->sum('collection.agility'),
            stamina: $recorded->sum('collection.stamina'),
            hpPercent: $percent,
            attackPercent: $percent,
        ));
    }
}

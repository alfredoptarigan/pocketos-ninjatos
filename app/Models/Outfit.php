<?php

namespace App\Models;

use Database\Factories\OutfitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A wearable character from the catalogue (database/data/outfits.json).
 *
 * @property int $id
 * @property string $key Asset key "<sex>_<id>", as for avatars
 * @property string $name
 * @property int $sex 0 male, 1 female
 * @property string $rarity grey, blue or orange
 * @property string|null $weapon_class blunt, sharp or gloves; null holds all three
 * @property array{strength: int, agility: int, stamina: int}|null $collection Attributes once recorded; null if not collectible
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'name', 'sex', 'rarity', 'collection', 'weapon_class'])]
class Outfit extends Model
{
    /** @use HasFactory<OutfitFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['collection' => 'array'];
    }

    /**
     * Outfits a Wishing Pot can give: not event-only.
     *
     * @param  Builder<Outfit>  $query
     */
    public function scopeInPots(Builder $query): void
    {
        $query->whereNotIn('key', config('game.outfits.event_only'));
    }

    /**
     * Health, attack and defense percent while worn: the rarity's bonus plus
     * config('game.outfits.upgrade.bonus_per_level') per upgrade level.
     */
    public function bonusPercent(int $level = 0): int
    {
        return config("game.outfits.bonus_percent.{$this->rarity}") + $level * config('game.outfits.upgrade.bonus_per_level');
    }

    /**
     * @return array<string, int|string>
     */
    public function summary(int $level = 0): array
    {
        return [...$this->only(['id', 'key', 'name', 'rarity']), 'level' => $level, 'bonus' => $this->bonusPercent($level)];
    }
}

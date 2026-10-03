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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'name', 'sex', 'rarity'])]
class Outfit extends Model
{
    /** @use HasFactory<OutfitFactory> */
    use HasFactory;

    /**
     * Outfits a Wishing Pot can give: not event-only.
     *
     * @param  Builder<Outfit>  $query
     */
    public function scopeInPots(Builder $query): void
    {
        $query->whereNotIn('key', config('game.outfits.event_only'));
    }

    public function bonusPercent(): int
    {
        return config("game.outfits.bonus_percent.{$this->rarity}");
    }

    /**
     * @return array<string, int|string>
     */
    public function summary(): array
    {
        return [...$this->only(['id', 'key', 'name', 'rarity']), 'bonus' => $this->bonusPercent()];
    }
}

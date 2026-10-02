<?php

namespace App\Models;

use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code Original Pockie Ninja item id, e.g. "i160006"
 * @property string $category
 * @property string $name
 * @property string $icon
 * @property int $price
 * @property int $restore_hp
 * @property int $restore_chakra
 * @property int $restore_energy
 * @property int $max_stack
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'category', 'name', 'icon', 'price', 'restore_hp', 'restore_chakra', 'restore_energy', 'max_stack'])]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    public const CATEGORY_PHARMACY = 'pharmacy';

    // Area keys that open hunting-ground caches.
    public const CATEGORY_KEY = 'key';

    /**
     * @param  Builder<Item>  $query
     */
    public function scopePharmacy(Builder $query): void
    {
        $query->where('category', self::CATEGORY_PHARMACY);
    }
}

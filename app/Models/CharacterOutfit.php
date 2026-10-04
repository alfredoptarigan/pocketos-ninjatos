<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * An outfit in a ninja's wardrobe (character_outfits).
 *
 * @property int $character_id
 * @property int $outfit_id
 * @property int $level Upgrade level (+0..+27)
 * @property Carbon|null $recorded_at When it was recorded in the avatar collection
 */
class CharacterOutfit extends Pivot
{
    protected $table = 'character_outfits';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['level' => 'integer', 'recorded_at' => 'datetime'];
    }
}

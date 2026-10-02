<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One piece of gear a ninja owns, worn or in the bag.
 *
 * @property int $id
 * @property int $character_id
 * @property int $equipment_id
 * @property string|null $equipped_slot
 * @property-read Equipment $equipment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CharacterEquipment extends Model
{
    protected $table = 'character_equipment';

    // Only game actions create pieces or change what is worn.
    protected $guarded = ['*'];

    /**
     * @return BelongsTo<Equipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}

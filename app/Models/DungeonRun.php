<?php

namespace App\Models;

use Database\Factories\DungeonRunFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $character_id
 * @property int $dungeon_id
 * @property int $stage Index of the stage being fought
 * @property int $wave Index of the next wave in that stage
 * @property string $status active, cleared, failed or left
 * @property-read Dungeon $dungeon
 * @property-read Character $character
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DungeonRun extends Model
{
    /** @use HasFactory<DungeonRunFactory> */
    use HasFactory;

    protected $attributes = ['stage' => 0, 'wave' => 0, 'status' => 'active'];

    /**
     * @param  Builder<DungeonRun>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    /**
     * @return BelongsTo<Dungeon, $this>
     */
    public function dungeon(): BelongsTo
    {
        return $this->belongsTo(Dungeon::class);
    }

    /**
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}

<?php

namespace App\Models;

use Database\Factories\FieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A hunting ground on the world map (database/data/fields.json).
 *
 * @property string $scene Original scene id, e.g. "2101"
 * @property string $name
 * @property string|null $village Owning village id, null for shared areas
 * @property int $level Level needed to enter
 * @property string $background
 * @property list<array{spot: string, key: string|null, cooldown: int, exp: int, rates: array<string, int>, art: array{image: string, x: int, y: int}}>|null $searches
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['scene', 'name', 'village', 'level', 'background', 'searches'])]
class Field extends Model
{
    /** @use HasFactory<FieldFactory> */
    use HasFactory;

    protected $primaryKey = 'scene';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['searches' => 'array'];
    }

    /**
     * @return HasMany<FieldMonster, $this>
     */
    public function monsters(): HasMany
    {
        return $this->hasMany(FieldMonster::class)->orderBy('level');
    }
}

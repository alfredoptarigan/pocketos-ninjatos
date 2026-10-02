<?php

namespace App\Models;

use App\Models\Concerns\FightsAsOpponent;
use Database\Factories\FieldMonsterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A monster roaming a hunting ground.
 *
 * @property int $id
 * @property string $field_scene
 * @property string $code Original npc id, e.g. "n33017"
 * @property string $name
 * @property bool $is_boss
 * @property int $level
 * @property int $max_hp
 * @property int $max_mp
 * @property int $min_atk
 * @property int $max_atk
 * @property int $defense
 * @property int $crit
 * @property int $crit_multiplier
 * @property int $dodge
 * @property int $parry
 * @property int $counter
 * @property int $priority
 * @property int $exp
 * @property array{type: string, face: string, motions?: string, portrait?: string} $art
 * @property-read Field $field
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'name', 'is_boss', 'level', 'max_hp', 'max_mp', 'min_atk', 'max_atk', 'defense', 'crit', 'crit_multiplier', 'dodge', 'parry', 'counter', 'priority', 'exp', 'art'])]
class FieldMonster extends Model
{
    use FightsAsOpponent;

    /** @use HasFactory<FieldMonsterFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_boss' => 'boolean', 'art' => 'array'];
    }

    /**
     * @return BelongsTo<Field, $this>
     */
    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class, 'field_scene');
    }
}

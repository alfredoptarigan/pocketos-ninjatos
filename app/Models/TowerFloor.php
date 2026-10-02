<?php

namespace App\Models;

use App\Game\Combatant;
use Database\Factories\TowerFloorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $floor
 * @property string $code Original opponent id, e.g. "n900001"
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
 */
#[Fillable(['floor', 'code', 'name', 'is_boss', 'level', 'max_hp', 'max_mp', 'min_atk', 'max_atk', 'defense', 'crit', 'crit_multiplier', 'dodge', 'parry', 'counter', 'priority', 'exp', 'art'])]
class TowerFloor extends Model
{
    /** @use HasFactory<TowerFloorFactory> */
    use HasFactory;

    // A few opponents have no crit multiplier in the data; treat them as double damage.
    private const DEFAULT_CRIT_MULTIPLIER = 200;

    protected $primaryKey = 'floor';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_boss' => 'boolean', 'art' => 'array'];
    }

    public function combatant(): Combatant
    {
        return new Combatant(
            name: $this->name,
            hp: $this->max_hp,
            maxHp: $this->max_hp,
            minAttack: $this->min_atk,
            maxAttack: $this->max_atk,
            defense: $this->defense,
            dodge: $this->dodge,
            crit: $this->crit,
            critMultiplier: $this->crit_multiplier ?: self::DEFAULT_CRIT_MULTIPLIER,
            parry: $this->parry,
            counter: $this->counter,
            priority: $this->priority,
        );
    }
}

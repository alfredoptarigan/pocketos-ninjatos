<?php

namespace App\Models;

use Database\Factories\BattleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $character_id
 * @property int|null $floor Tower floor, for tower battles
 * @property int|null $field_monster_id Monster fought, for hunting-ground battles
 * @property-read FieldMonster|null $fieldMonster
 * @property int|null $dungeon_run_id Run this wave belonged to, for dungeon battles
 * @property-read DungeonRun|null $dungeonRun
 * @property bool $won
 * @property array{fighters: array<int, array<string, mixed>>, events: list<array<string, mixed>>} $log
 * @property array{exp: int, gold: int, levelUp: bool, firstClear: bool} $rewards
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['floor', 'field_monster_id', 'dungeon_run_id', 'won', 'log', 'rewards'])]
class Battle extends Model
{
    /** @use HasFactory<BattleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['won' => 'boolean', 'log' => 'array', 'rewards' => 'array'];
    }

    /**
     * @return BelongsTo<FieldMonster, $this>
     */
    public function fieldMonster(): BelongsTo
    {
        return $this->belongsTo(FieldMonster::class);
    }

    /**
     * @return BelongsTo<DungeonRun, $this>
     */
    public function dungeonRun(): BelongsTo
    {
        return $this->belongsTo(DungeonRun::class);
    }

    /**
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}

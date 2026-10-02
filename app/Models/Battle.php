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
 * @property int $floor
 * @property bool $won
 * @property array{fighters: array<int, array<string, mixed>>, events: list<array<string, mixed>>} $log
 * @property array{exp: int, gold: int, levelUp: bool, firstClear: bool} $rewards
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['floor', 'won', 'log', 'rewards'])]
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
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}

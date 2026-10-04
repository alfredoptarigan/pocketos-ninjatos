<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * A completed achievement (character_achievements).
 *
 * @property int $character_id
 * @property int $achievement_id
 * @property Carbon|null $completed_at
 */
class CharacterAchievement extends Pivot
{
    protected $table = 'character_achievements';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }
}

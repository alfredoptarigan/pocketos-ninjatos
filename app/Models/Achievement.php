<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A tracked achievement (config('game.achievements.tracked')).
 *
 * @property int $id Original accomplishment id
 * @property string $name
 * @property string $counter Key of config('game.achievements.counters')
 * @property int $target
 * @property int $points
 * @property string|null $title Code of the title it grants
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['id', 'name', 'counter', 'target', 'points', 'title'])]
class Achievement extends Model
{
    public $incrementing = false;

    /**
     * "Reach level 21.", from the counter's goal text.
     */
    public function goal(): string
    {
        return str_replace('{target}', number_format($this->target), config("game.achievements.counters.{$this->counter}"));
    }
}

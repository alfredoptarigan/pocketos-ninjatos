<?php

namespace App\Models;

use App\Game\StatBonus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A title from the catalogue (database/data/titles.json).
 *
 * @property int $id Original title id
 * @property string $code Original name key, e.g. "EffortTitle01", used by achievements and the collection
 * @property string $name
 * @property int $category Original title group (1 level, 3 collection, ...)
 * @property array{strength?: int, agility?: int, stamina?: int, hp?: int, attack?: int, defense?: int, dodge?: int, crit?: int, hpPercent?: float, attackPercent?: float, defensePercent?: float} $bonus StatBonus fields while worn
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['id', 'code', 'name', 'category', 'bonus'])]
class Title extends Model
{
    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['bonus' => 'array'];
    }

    public function statBonus(): StatBonus
    {
        return new StatBonus(...$this->bonus);
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return $this->only(['id', 'code', 'name', 'bonus']);
    }
}

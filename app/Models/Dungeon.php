<?php

namespace App\Models;

use App\Game\DungeonMonster;
use Database\Factories\DungeonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An original tollgate: stages of five waves, each wave fought against its leader.
 *
 * @property int $id
 * @property string $code Original tollgate id, e.g. "100001"
 * @property string $name
 * @property string $difficulty trial, normal or hard
 * @property int $min_level
 * @property int $max_level
 * @property int $daily_runs
 * @property int $reward_exp Paid on a full clear
 * @property int $reward_gold Paid on a full clear
 * @property string $picture
 * @property list<array{name: string, recommended: string, reward_exp: int, reward_gold: int, waves: list<array<string, mixed>>}> $stages
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'name', 'difficulty', 'min_level', 'max_level', 'daily_runs', 'reward_exp', 'reward_gold', 'picture', 'stages'])]
class Dungeon extends Model
{
    /** @use HasFactory<DungeonFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['stages' => 'array'];
    }

    /**
     * @return HasMany<DungeonRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(DungeonRun::class);
    }

    /**
     * A wave's leader. Stage bosses were built for two-player teams, so their
     * health and attack shrink to config('game.dungeons.solo_boss_percent').
     */
    public function monster(int $stage, int $wave): DungeonMonster
    {
        $leader = $this->stages[$stage]['waves'][$wave];

        if ($leader['is_boss']) {
            $percent = config('game.dungeons.solo_boss_percent');
            foreach (['max_hp', 'min_atk', 'max_atk'] as $stat) {
                $leader[$stat] = max(1, intdiv($leader[$stat] * $percent, 100));
            }
        }

        return DungeonMonster::fromWave($leader);
    }

    public function runsLeftToday(Character $character): int
    {
        $used = $this->runs()->whereBelongsTo($character)->where('created_at', '>=', Carbon::today())->count();

        return max(0, $this->daily_runs - $used);
    }
}

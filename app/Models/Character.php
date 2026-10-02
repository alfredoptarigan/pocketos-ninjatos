<?php

namespace App\Models;

use App\Game\Combatant;
use App\Game\CombatStats;
use App\Game\Leveling;
use App\Game\Skill;
use App\Game\Vitals;
use Database\Factories\CharacterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $avatar Asset key "<sex>_<id>" from config('game.avatars')
 * @property int $level
 * @property int $exp Experience into the current level
 * @property int|null $hp Health at vitals_at; null means full
 * @property int|null $mp Chakra at vitals_at; null means full
 * @property Carbon|null $vitals_at
 * @property int $gold
 * @property string $village Key of config('game.villages')
 * @property int $tower_floor Highest Training Tower floor cleared
 * @property list<string> $skills Learned jutsu ids (config('game.skills'))
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'avatar'])]
class Character extends Model
{
    /** @use HasFactory<CharacterFactory> */
    use HasFactory;

    protected $attributes = [
        'level' => 1,
        'exp' => 0,
        'tower_floor' => 0,
        'skills' => '[]',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['vitals_at' => 'datetime', 'skills' => 'array'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<InventoryItem, $this>
     */
    public function inventory(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    /**
     * @return HasMany<Battle, $this>
     */
    public function battles(): HasMany
    {
        return $this->hasMany(Battle::class);
    }

    public function stats(): CombatStats
    {
        return CombatStats::for($this);
    }

    public function currentHp(): int
    {
        $max = $this->stats()->maxHp;

        return $this->hp === null ? $max : Vitals::recovered($this->hp, $max, $this->vitals_at, Carbon::now());
    }

    public function currentMp(): int
    {
        $max = $this->stats()->maxMp;

        return $this->mp === null ? $max : Vitals::recovered($this->mp, $max, $this->vitals_at, Carbon::now());
    }

    /**
     * Store health and chakra as of now, so later regeneration starts from here.
     */
    public function setVitals(int $hp, int $mp): void
    {
        $this->forceFill(['hp' => $hp, 'mp' => $mp, 'vitals_at' => Carbon::now()]);
    }

    public function combatant(): Combatant
    {
        $stats = $this->stats();

        return new Combatant(
            name: $this->name,
            hp: $this->currentHp(),
            maxHp: $stats->maxHp,
            minAttack: $stats->minAttack,
            maxAttack: $stats->maxAttack,
            defense: $stats->defense,
            dodge: $stats->dodge,
            crit: $stats->crit,
            critMultiplier: $stats->critMultiplier,
            parry: $stats->parry,
            counter: 0,
            priority: 0,
            mp: $this->currentMp(),
            maxMp: $stats->maxMp,
            skills: $this->learnedSkills(),
        );
    }

    /**
     * Learned jutsu in config order, so their trigger order is stable.
     *
     * @return list<Skill>
     */
    public function learnedSkills(): array
    {
        return collect(array_keys(config('game.skills')))
            ->map(fn (string|int $id) => (string) $id)
            ->filter(fn (string $id) => in_array($id, $this->skills, true))
            ->map(fn (string $id) => Skill::find($id))
            ->values()
            ->all();
    }

    /**
     * What the game HUD shows on every page.
     *
     * @return array<string, int|string>
     */
    public function hud(): array
    {
        $stats = $this->stats();

        return [
            'name' => $this->name,
            'avatar' => $this->avatar,
            'level' => $this->level,
            'exp' => $this->exp,
            'exp_to_next' => Leveling::expToNext($this->level),
            'hp' => $this->currentHp(),
            'max_hp' => $stats->maxHp,
            'mp' => $this->currentMp(),
            'max_mp' => $stats->maxMp,
            'gold' => $this->gold,
            'village' => $this->village,
        ];
    }
}

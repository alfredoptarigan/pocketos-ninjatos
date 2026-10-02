<?php

namespace App\Models\Concerns;

use App\Game\Combatant;

/**
 * Turns a row with the original npc stat columns (tower floors, field monsters) into a fighter.
 *
 * @property string $name
 * @property int $max_hp
 * @property int $min_atk
 * @property int $max_atk
 * @property int $defense
 * @property int $crit
 * @property int $crit_multiplier
 * @property int $dodge
 * @property int $parry
 * @property int $counter
 * @property int $priority
 */
trait FightsAsOpponent
{
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
            // A few opponents have no crit multiplier in the data; treat them as double damage.
            critMultiplier: $this->crit_multiplier ?: 200,
            parry: $this->parry,
            counter: $this->counter,
            priority: $this->priority,
        );
    }
}

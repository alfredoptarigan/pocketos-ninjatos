<?php

namespace App\Models;

use Database\Factories\EquipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A piece of gear from the catalogue (database/data/equipment.json).
 *
 * @property int $id
 * @property string $code Original item id, e.g. "i250101"
 * @property string $name
 * @property string $slot One of config('game.equipment.slots')
 * @property int $level Level needed to wear it
 * @property string $icon
 * @property int $price
 * @property int $min_attack
 * @property int $max_attack
 * @property int $defense
 * @property int $max_hp
 * @property int $crit Critical chance bonus, in percent
 * @property string|null $look How a weapon looks in hand, e.g. "sharp20" (weapon motions)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'name', 'slot', 'level', 'icon', 'price', 'min_attack', 'max_attack', 'defense', 'max_hp', 'crit', 'look'])]
class Equipment extends Model
{
    /** @use HasFactory<EquipmentFactory> */
    use HasFactory;

    protected $table = 'equipment';

    /**
     * What the Equipment Shop charges: the original price, scaled up with the gear's level.
     */
    public function buyPrice(): int
    {
        return $this->price * (config('game.equipment.buy_multiplier') + $this->level);
    }

    /**
     * What the Equipment Shop pays for it.
     */
    public function sellPrice(): int
    {
        return intdiv($this->buyPrice() * config('game.equipment.sell_percent'), 100);
    }

    /**
     * @return array<string, int|string>
     */
    public function summary(): array
    {
        return $this->only(['code', 'name', 'slot', 'level', 'icon', 'min_attack', 'max_attack', 'defense', 'max_hp', 'crit']);
    }
}

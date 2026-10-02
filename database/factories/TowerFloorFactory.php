<?php

namespace Database\Factories;

use App\Models\TowerFloor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TowerFloor>
 */
class TowerFloorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $floor = fake()->unique()->numberBetween(1, 170);

        return [
            'floor' => $floor,
            'code' => sprintf('n9%05d', $floor),
            'name' => 'Rudbornn',
            'is_boss' => false,
            'level' => 1,
            'max_hp' => 40,
            'max_mp' => 110,
            'min_atk' => 14,
            'max_atk' => 17,
            'defense' => 0,
            'crit' => 0,
            'crit_multiplier' => 230,
            'dodge' => 2,
            'parry' => 0,
            'counter' => 0,
            'priority' => 0,
            'exp' => 355,
            'art' => ['type' => 'motion', 'motions' => '/game-assets/monsters/n32051/motions.json', 'face' => '/game-assets/monsters/n32051/face.png'],
        ];
    }
}

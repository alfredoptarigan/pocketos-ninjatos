<?php

namespace Database\Factories;

use App\Models\Field;
use App\Models\FieldMonster;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FieldMonster>
 */
class FieldMonsterFactory extends Factory
{
    /**
     * Define the model's default state: an easy foe.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'field_scene' => Field::factory(),
            'code' => fake()->unique()->numerify('n33###'),
            'name' => 'Sunflower',
            'is_boss' => false,
            'level' => 2,
            'max_hp' => 1,
            'max_mp' => 100,
            'min_atk' => 5,
            'max_atk' => 6,
            'defense' => 0,
            'crit' => 0,
            'crit_multiplier' => 200,
            'dodge' => 0,
            'parry' => 0,
            'counter' => 0,
            'priority' => 0,
            'exp' => 8,
            'art' => ['type' => 'motion', 'motions' => '/game-assets/monsters/n10001/motions.json', 'face' => '/game-assets/monsters/n10001/face.png'],
        ];
    }
}

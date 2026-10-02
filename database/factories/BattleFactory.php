<?php

namespace Database\Factories;

use App\Models\Battle;
use App\Models\Character;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Battle>
 */
class BattleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'floor' => 1,
            'won' => true,
            'log' => ['fighters' => [], 'events' => [['type' => 'end', 'winner' => 0, 'reason' => 'ko']]],
            'rewards' => ['exp' => 0, 'gold' => 0, 'levelUp' => false, 'firstClear' => false],
        ];
    }
}

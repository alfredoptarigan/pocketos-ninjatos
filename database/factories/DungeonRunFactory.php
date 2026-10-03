<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\Dungeon;
use App\Models\DungeonRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DungeonRun>
 */
class DungeonRunFactory extends Factory
{
    /**
     * Define the model's default state: a run just started.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'dungeon_id' => Dungeon::factory(),
            'stage' => 0,
            'wave' => 0,
            'status' => 'active',
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Character>
 */
class CharacterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->lexify('ninja????'),
            'avatar' => fake()->randomElement(array_keys(config('game.avatars'))),
            'gold' => config('game.starting_gold'),
            'village' => config('game.home_village'),
            'tower_floor' => 0,
        ];
    }
}

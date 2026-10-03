<?php

namespace Database\Factories;

use App\Models\Outfit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Outfit>
 */
class OutfitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => '0_'.fake()->unique()->numberBetween(1, 99),
            'name' => fake()->name(),
            'sex' => 0,
            'rarity' => 'grey',
        ];
    }
}

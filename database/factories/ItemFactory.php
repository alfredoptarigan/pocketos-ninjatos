<?php

namespace Database\Factories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = fake()->unique()->numerify('i16####');

        return [
            'code' => $code,
            'category' => Item::CATEGORY_PHARMACY,
            'name' => fake()->words(2, true),
            'icon' => "/game-assets/items/{$code}.gif",
            'price' => fake()->numberBetween(10, 100),
            'restore_hp' => 150,
            'restore_chakra' => 0,
            'restore_energy' => 0,
            'max_stack' => 100,
        ];
    }
}

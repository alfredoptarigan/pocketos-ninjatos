<?php

namespace Database\Factories;

use App\Models\Field;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Field>
 */
class FieldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $scene = (string) fake()->unique()->numberBetween(2101, 2799);

        return [
            'scene' => $scene,
            'name' => 'Furnace Highlands',
            'village' => null,
            'level' => 1,
            'background' => "/game-assets/fields/{$scene}.jpg",
        ];
    }
}

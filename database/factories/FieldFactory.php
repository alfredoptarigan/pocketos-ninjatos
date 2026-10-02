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

    /**
     * A free search spot and a cache opened with `$key`, both always rolling `$outcome`.
     */
    public function searchable(string $outcome, ?string $key = 'i150046'): static
    {
        $rates = ['monster' => 0, 'money' => 0, 'key' => 0, 'boss' => 0, 'baby' => 0, 'item' => 0, $outcome => 10000];
        $art = ['image' => '/game-assets/fields/2101/search.png', 'x' => 0, 'y' => 0];

        return $this->state(['searches' => [
            ['spot' => 'search', 'key' => null, 'cooldown' => 120, 'exp' => 105, 'rates' => $rates, 'art' => $art],
            ['spot' => 'cache', 'key' => $key, 'cooldown' => 120, 'exp' => 210, 'rates' => $rates, 'art' => $art],
        ]]);
    }
}

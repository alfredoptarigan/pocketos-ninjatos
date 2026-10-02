<?php

namespace Database\Factories;

use App\Models\Equipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipment>
 */
class EquipmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = fake()->unique()->numerify('i25####');

        return [
            'code' => $code,
            'name' => fake()->words(2, true),
            'slot' => 'weapon',
            'level' => 1,
            'icon' => "/game-assets/equipment/{$code}.png",
            'price' => 44,
            'min_attack' => 14,
            'max_attack' => 16,
            'defense' => 0,
            'max_hp' => 0,
            'crit' => 0,
        ];
    }

    /**
     * @param  array<string, int>  $stats
     */
    public function slot(string $slot, int $level = 1, array $stats = []): static
    {
        return $this->state([
            'slot' => $slot,
            'level' => $level,
            'min_attack' => 0,
            'max_attack' => 0,
            ...$stats,
        ]);
    }
}

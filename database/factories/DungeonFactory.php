<?php

namespace Database\Factories;

use App\Models\Dungeon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dungeon>
 */
class DungeonFactory extends Factory
{
    /**
     * Define the model's default state: one stage of two easy waves.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('100###'),
            'name' => 'Valhalla Camp',
            'difficulty' => 'normal',
            'min_level' => 16,
            'max_level' => 25,
            'daily_runs' => 3,
            'reward_exp' => 500,
            'reward_gold' => 2800,
            'picture' => '/game-assets/dungeons/100001.jpg',
            'stages' => [self::stage(2)],
        ];
    }

    /**
     * A stage of `$waves` easy waves; the last one is the boss.
     *
     * @param  array<string, mixed>  $monster  stat overrides for every wave
     * @return array<string, mixed>
     */
    public static function stage(int $waves, array $monster = []): array
    {
        return [
            'name' => 'Camp Outpost',
            'recommended' => '15-16',
            'reward_exp' => 0,
            'reward_gold' => 100,
            'waves' => array_map(fn (int $index) => [
                'code' => 'n30111'.$index,
                'name' => 'Demon Soldier',
                'is_boss' => $index === $waves - 1,
                'level' => 15,
                'max_hp' => 1,
                'max_mp' => 100,
                'min_atk' => 1,
                'max_atk' => 1,
                'defense' => 0,
                'crit' => 0,
                'crit_multiplier' => 200,
                'dodge' => 0,
                'parry' => 0,
                'counter' => 0,
                'priority' => 0,
                'exp' => 10,
                'art' => ['type' => 'motion', 'motions' => '/game-assets/monsters/n10001/motions.json', 'face' => '/game-assets/monsters/n10001/face.png'],
                ...$monster,
            ], range(0, $waves - 1)),
        ];
    }
}

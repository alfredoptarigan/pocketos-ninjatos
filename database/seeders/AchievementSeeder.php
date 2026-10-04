<?php

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use RuntimeException;

class AchievementSeeder extends Seeder
{
    private const DATA = 'database/data/achievements.json';

    /**
     * Load the tracked achievements: names and counters from config, target,
     * points and title from the original data.
     */
    public function run(): void
    {
        $path = base_path(self::DATA);

        if (! is_file($path)) {
            throw new RuntimeException(
                'Achievement data not found. Run: python3 tools/extract_progression_data.py ~/Privates/game-pockieninja',
            );
        }

        $original = collect(File::json($path, JSON_THROW_ON_ERROR))->keyBy('id');

        foreach (config('game.achievements.tracked') as $id => $tracked) {
            $data = $original[$id] ?? throw new RuntimeException("Achievement {$id} is not in the original data.");
            Achievement::updateOrCreate(['id' => $id], [
                ...$tracked,
                'target' => $data['target'],
                'points' => $data['points'],
                'title' => $data['title'],
            ]);
        }
    }
}

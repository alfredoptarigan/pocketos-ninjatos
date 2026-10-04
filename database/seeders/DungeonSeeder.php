<?php

namespace Database\Seeders;

use App\Models\Dungeon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use RuntimeException;

class DungeonSeeder extends Seeder
{
    private const DATA = 'database/data/dungeons.json';

    /**
     * Load the dungeons extracted from the original game data.
     */
    public function run(): void
    {
        $path = base_path(self::DATA);

        if (! is_file($path)) {
            throw new RuntimeException(
                'Dungeon data not found. Run: python3 tools/extract_dungeon_assets.py ~/Privates/game-pockieninja',
            );
        }

        foreach (File::json($path, JSON_THROW_ON_ERROR) as $dungeon) {
            Dungeon::updateOrCreate(['code' => $dungeon['code']], $dungeon);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\TowerFloor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use RuntimeException;

class TowerSeeder extends Seeder
{
    private const DATA = 'database/data/tower.json';

    /**
     * Load the Training Tower floors extracted from the original game data.
     */
    public function run(): void
    {
        $path = base_path(self::DATA);

        if (! is_file($path)) {
            throw new RuntimeException(
                'Tower data not found. Run: python3 tools/extract_tower_assets.py ~/Privates/game-pockieninja',
            );
        }

        foreach (File::json($path, JSON_THROW_ON_ERROR) as $floor) {
            TowerFloor::updateOrCreate(['floor' => $floor['floor']], $floor);
        }
    }
}

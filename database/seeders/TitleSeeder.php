<?php

namespace Database\Seeders;

use App\Models\Title;
use Illuminate\Database\Seeder;
use RuntimeException;

class TitleSeeder extends Seeder
{
    private const DATA = 'database/data/titles.json';

    /**
     * Load the titles extracted from the original game data.
     */
    public function run(): void
    {
        $path = base_path(self::DATA);

        if (! is_file($path)) {
            throw new RuntimeException(
                'Title data not found. Run: python3 tools/extract_progression_data.py ~/Privates/game-pockieninja',
            );
        }

        foreach (json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR) as $title) {
            Title::updateOrCreate(['id' => $title['id']], $title);
        }
    }
}

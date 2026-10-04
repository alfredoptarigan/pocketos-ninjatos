<?php

namespace Database\Seeders;

use App\Models\Outfit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use RuntimeException;

class OutfitSeeder extends Seeder
{
    private const DATA = 'database/data/outfits.json';

    private const COLLECTION = 'database/data/avatar_collection.json';

    /**
     * Load the outfit catalogue extracted from the original game data.
     */
    public function run(): void
    {
        $path = base_path(self::DATA);

        if (! is_file($path)) {
            throw new RuntimeException(
                'Outfit data not found. Run: python3 tools/extract_outfit_assets.py ~/Privates/game-pockieninja',
            );
        }

        // Attributes of collectible outfits by outfit id (tools/extract_progression_data.py).
        $collection = is_file(base_path(self::COLLECTION))
            ? File::json(base_path(self::COLLECTION), JSON_THROW_ON_ERROR)
            : [];

        foreach (File::json($path, JSON_THROW_ON_ERROR) as $outfit) {
            $id = explode('_', $outfit['key'])[1];
            Outfit::updateOrCreate(['key' => $outfit['key']], [...$outfit, 'collection' => $collection[$id] ?? null]);
        }
    }
}

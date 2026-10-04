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

    // Characters made for this rework (tools/import_custom_character.py), kept
    // apart so re-extracting the original outfits leaves them alone.
    private const CUSTOM = 'database/data/custom_outfits.json';

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

        $custom = is_file(base_path(self::CUSTOM)) ? File::json(base_path(self::CUSTOM), JSON_THROW_ON_ERROR) : [];

        foreach ([...File::json($path, JSON_THROW_ON_ERROR), ...$custom] as $outfit) {
            $id = explode('_', $outfit['key'])[1];
            Outfit::updateOrCreate(['key' => $outfit['key']], [...$outfit, 'collection' => $collection[$id] ?? null]);
        }
    }
}

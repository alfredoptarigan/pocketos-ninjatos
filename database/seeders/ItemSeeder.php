<?php

namespace Database\Seeders;

use App\Models\Item;
use Illuminate\Database\Seeder;
use RuntimeException;

class ItemSeeder extends Seeder
{
    private const PHARMACY_DATA = 'database/data/pharmacy_items.json';

    /**
     * Load shop items extracted from the original game data.
     */
    public function run(): void
    {
        $path = base_path(self::PHARMACY_DATA);

        if (! is_file($path)) {
            throw new RuntimeException(
                'Item data not found. Run: python3 tools/extract_item_assets.py ~/Privates/game-pockieninja',
            );
        }

        $items = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        foreach ($items as $item) {
            Item::updateOrCreate(
                ['code' => $item['code']],
                [...$item, 'category' => Item::CATEGORY_PHARMACY],
            );
        }
    }
}

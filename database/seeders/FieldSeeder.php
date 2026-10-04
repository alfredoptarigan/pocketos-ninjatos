<?php

namespace Database\Seeders;

use App\Models\Field;
use App\Models\Item;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use RuntimeException;

class FieldSeeder extends Seeder
{
    private const DATA = 'database/data/fields.json';

    /**
     * Load the world map's hunting grounds extracted from the original game data.
     */
    public function run(): void
    {
        $path = base_path(self::DATA);

        if (! is_file($path)) {
            throw new RuntimeException(
                'Field data not found. Run: python3 tools/extract_world_assets.py ~/Privates/game-pockieninja',
            );
        }

        foreach (File::json($path, JSON_THROW_ON_ERROR) as $data) {
            $field = Field::updateOrCreate(['scene' => $data['scene']], Arr::except($data, ['monsters', 'key_item']));

            if ($data['key_item']) {
                Item::updateOrCreate(['code' => $data['key_item']['code']], [
                    ...$data['key_item'],
                    'category' => Item::CATEGORY_KEY,
                    'price' => 0,
                    'max_stack' => 100,
                ]);
            }

            foreach ($data['monsters'] as $monster) {
                $field->monsters()->updateOrCreate(['code' => $monster['code']], $monster);
            }
        }
    }
}

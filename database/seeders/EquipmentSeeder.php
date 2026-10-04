<?php

namespace Database\Seeders;

use App\Models\Equipment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use RuntimeException;

class EquipmentSeeder extends Seeder
{
    private const DATA = 'database/data/equipment.json';

    /**
     * Load the equipment catalogue extracted from the original game data.
     */
    public function run(): void
    {
        $path = base_path(self::DATA);

        if (! is_file($path)) {
            throw new RuntimeException(
                'Equipment data not found. Run: python3 tools/extract_equipment_assets.py ~/Privates/game-pockieninja',
            );
        }

        foreach (File::json($path, JSON_THROW_ON_ERROR) as $piece) {
            Equipment::updateOrCreate(['code' => $piece['code']], $piece);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\Outfit;
use Database\Seeders\OutfitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OutfitSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_characters_load_alongside_the_extracted_outfits()
    {
        $base = sys_get_temp_dir().'/outfit-seeder-'.uniqid();
        File::ensureDirectoryExists("$base/database/data");
        File::put("$base/database/data/outfits.json", json_encode([
            ['key' => '0_1', 'name' => 'Kurosaki Ichigo', 'sex' => 0, 'rarity' => 'orange', 'weapon_class' => 'sharp'],
        ]));
        File::put("$base/database/data/custom_outfits.json", json_encode([
            ['key' => '0_201', 'name' => 'Custom Hero', 'sex' => 0, 'rarity' => 'blue', 'weapon_class' => 'gloves'],
        ]));
        $original = $this->app->basePath();
        $this->app->setBasePath($base);

        try {
            $this->seed(OutfitSeeder::class);
        } finally {
            $this->app->setBasePath($original);
            File::deleteDirectory($base);
        }

        $this->assertSame(['0_1', '0_201'], Outfit::orderBy('key')->pluck('key')->all());
        $this->assertSame('gloves', Outfit::where('key', '0_201')->value('weapon_class'));
    }
}

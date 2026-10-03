<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Wearable characters from database/data/outfits.json (OutfitSeeder).
        Schema::create('outfits', function (Blueprint $table) {
            $table->id();
            $table->string('key', 8)->unique();
            $table->string('name');
            $table->unsignedTinyInteger('sex');
            $table->string('rarity', 8);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outfits');
    }
};

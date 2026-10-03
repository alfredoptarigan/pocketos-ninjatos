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
        // Outfit upgrades: the +N of each owned outfit, paid with shards from duplicate draws.
        Schema::table('character_outfits', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->default(0);
        });
        Schema::table('characters', function (Blueprint $table) {
            $table->unsignedInteger('outfit_shards')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('character_outfits', function (Blueprint $table) {
            $table->dropColumn('level');
        });
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('outfit_shards');
        });
    }
};

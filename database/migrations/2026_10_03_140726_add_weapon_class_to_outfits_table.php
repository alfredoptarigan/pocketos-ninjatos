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
        // The weapons an outfit fights with: "blunt", "sharp" or "gloves"; null for all three.
        Schema::table('outfits', function (Blueprint $table) {
            $table->string('weapon_class', 8)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outfits', function (Blueprint $table) {
            $table->dropColumn('weapon_class');
        });
    }
};

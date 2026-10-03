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
        // Avatar collection: what an outfit gives once recorded (null: not collectible),
        // and when the ninja recorded it.
        Schema::table('outfits', function (Blueprint $table) {
            $table->json('collection')->nullable();
        });
        Schema::table('character_outfits', function (Blueprint $table) {
            $table->timestamp('recorded_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outfits', function (Blueprint $table) {
            $table->dropColumn('collection');
        });
        Schema::table('character_outfits', function (Blueprint $table) {
            $table->dropColumn('recorded_at');
        });
    }
};

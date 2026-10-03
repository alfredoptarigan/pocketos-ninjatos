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
        // Original tollgates from database/data/dungeons.json (DungeonSeeder).
        Schema::create('dungeons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            $table->string('name');
            $table->string('difficulty', 8);
            $table->unsignedSmallInteger('min_level');
            $table->unsignedSmallInteger('max_level');
            $table->unsignedTinyInteger('daily_runs');
            $table->unsignedInteger('reward_exp');
            $table->unsignedInteger('reward_gold');
            $table->string('picture');
            // Stages in order, each with its waves' leaders (stats and art), as extracted.
            $table->json('stages');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dungeons');
    }
};

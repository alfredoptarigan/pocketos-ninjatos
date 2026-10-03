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
        // Completed achievements.
        Schema::create('character_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('achievement_id');
            $table->foreign('achievement_id')->references('id')->on('achievements')->cascadeOnDelete();
            $table->timestamp('completed_at');
            $table->unique(['character_id', 'achievement_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('character_achievements');
    }
};

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
        // A ninja's way through a dungeon; created_at counts the daily runs.
        Schema::create('dungeon_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dungeon_id')->constrained()->cascadeOnDelete();
            // Next wave to fight, as indexes into dungeons.stages.
            $table->unsignedTinyInteger('stage')->default(0);
            $table->unsignedTinyInteger('wave')->default(0);
            $table->string('status', 8)->default('active');
            $table->timestamps();
            $table->index(['character_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dungeon_runs');
    }
};

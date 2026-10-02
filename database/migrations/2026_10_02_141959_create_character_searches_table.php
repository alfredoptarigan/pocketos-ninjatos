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
        // When a ninja last searched a spot, for its cooldown.
        Schema::create('character_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('field_scene', 8);
            $table->foreign('field_scene')->references('scene')->on('fields')->cascadeOnDelete();
            $table->string('spot', 8);
            $table->timestamp('searched_at');
            $table->unique(['character_id', 'field_scene', 'spot']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('character_searches');
    }
};

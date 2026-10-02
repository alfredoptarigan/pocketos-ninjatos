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
        // Hunting grounds on the world map (original "out city" scenes).
        Schema::create('fields', function (Blueprint $table) {
            // Original scene id, e.g. "2101".
            $table->string('scene', 8)->primary();
            $table->string('name', 64);
            // The village that owns the area, or null for shared areas.
            $table->string('village', 8)->nullable();
            $table->unsignedSmallInteger('level');
            $table->string('background');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fields');
    }
};

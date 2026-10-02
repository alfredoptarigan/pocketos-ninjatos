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
        // Pieces a ninja owns: one row per piece, since gear does not stack.
        Schema::create('character_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained()->restrictOnDelete();
            // The slot it is worn in, or null while it sits in the bag.
            $table->string('equipped_slot', 16)->nullable();
            $table->timestamps();
            // One piece per slot; NULLs (unworn pieces) never collide.
            $table->unique(['character_id', 'equipped_slot']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('character_equipment');
    }
};

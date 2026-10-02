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
        Schema::create('field_monsters', function (Blueprint $table) {
            $table->id();
            $table->string('field_scene', 8);
            $table->foreign('field_scene')->references('scene')->on('fields')->cascadeOnDelete();
            // Original npc id, e.g. "n33017".
            $table->string('code', 16);
            $table->string('name', 64);
            $table->boolean('is_boss');
            $table->unsignedSmallInteger('level');
            $table->unsignedInteger('max_hp');
            $table->unsignedInteger('max_mp');
            $table->unsignedInteger('min_atk');
            $table->unsignedInteger('max_atk');
            $table->unsignedInteger('defense');
            $table->unsignedSmallInteger('crit');
            $table->unsignedSmallInteger('crit_multiplier');
            $table->unsignedSmallInteger('dodge');
            $table->unsignedSmallInteger('parry');
            $table->unsignedSmallInteger('counter');
            $table->unsignedSmallInteger('priority');
            $table->unsignedInteger('exp');
            $table->json('art');
            $table->timestamps();
            $table->unique(['field_scene', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('field_monsters');
    }
};

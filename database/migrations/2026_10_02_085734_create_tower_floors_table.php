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
        // Training Tower floors, one opponent each (original singlegatenpc table).
        Schema::create('tower_floors', function (Blueprint $table) {
            $table->unsignedSmallInteger('floor')->primary();
            $table->string('code', 16)->unique();
            $table->string('name', 64);
            $table->boolean('is_boss')->default(false);
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
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tower_floors');
    }
};

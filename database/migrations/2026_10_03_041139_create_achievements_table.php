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
        // The tracked achievements (AchievementSeeder); ids are the original ones.
        Schema::create('achievements', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('name');
            $table->string('counter', 32);
            $table->unsignedBigInteger('target');
            $table->unsignedSmallInteger('points');
            $table->string('title', 32)->nullable();
            $table->timestamps();
        });

        // Counters behind the achievements that the character row does not hold yet.
        Schema::table('characters', function (Blueprint $table) {
            $table->unsignedBigInteger('gold_spent')->default(0);
            $table->unsignedInteger('bosses_defeated')->default(0);
            $table->unsignedInteger('sign_in_days')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(['gold_spent', 'bosses_defeated', 'sign_in_days']);
        });
        Schema::dropIfExists('achievements');
    }
};

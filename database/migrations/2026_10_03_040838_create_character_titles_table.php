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
        // Titles the ninja earned, and the one worn.
        Schema::create('character_titles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('title_id');
            $table->foreign('title_id')->references('id')->on('titles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['character_id', 'title_id']);
        });
        Schema::table('characters', function (Blueprint $table) {
            $table->unsignedInteger('title_id')->nullable();
            $table->foreign('title_id')->references('id')->on('titles')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropForeign(['title_id']);
            $table->dropColumn('title_id');
        });
        Schema::dropIfExists('character_titles');
    }
};

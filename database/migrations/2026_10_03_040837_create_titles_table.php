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
        // Titles from database/data/titles.json (TitleSeeder); ids are the original ones.
        Schema::create('titles', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->unsignedTinyInteger('category');
            $table->json('bonus');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('titles');
    }
};

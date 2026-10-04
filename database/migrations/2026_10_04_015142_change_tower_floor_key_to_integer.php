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
        // PostgreSQL reports smallint as int2, which Wayfinder types as a string
        // (SQLite says integer: a number), so the generated tower route differed
        // between local and CI. An integer key is a number everywhere.
        Schema::table('tower_floors', function (Blueprint $table) {
            $table->unsignedInteger('floor')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tower_floors', function (Blueprint $table) {
            $table->unsignedSmallInteger('floor')->change();
        });
    }
};

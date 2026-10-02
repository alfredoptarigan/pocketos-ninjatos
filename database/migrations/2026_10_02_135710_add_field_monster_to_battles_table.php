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
        // A battle is fought either on a tower floor or against a field monster.
        Schema::table('battles', function (Blueprint $table) {
            $table->unsignedSmallInteger('floor')->nullable()->change();
            $table->foreignId('field_monster_id')->nullable()->after('floor')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('battles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('field_monster_id');
            $table->unsignedSmallInteger('floor')->nullable(false)->change();
        });
    }
};

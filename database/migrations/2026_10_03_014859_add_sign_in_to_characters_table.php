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
        Schema::table('characters', function (Blueprint $table) {
            // Daily sign-in: day of the current 7-day streak and when it was last claimed.
            $table->unsignedTinyInteger('sign_in_streak')->default(0);
            $table->date('signed_in_on')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(['sign_in_streak', 'signed_in_on']);
        });
    }
};

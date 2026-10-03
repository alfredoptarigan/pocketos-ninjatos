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
            // Honor ever earned (sets the rank), medals to spend, and today's Honor Exchanges.
            $table->unsignedInteger('honor')->default(0);
            $table->unsignedInteger('medals')->default(0);
            $table->unsignedTinyInteger('honor_exchanges')->default(0);
            $table->date('honor_exchanged_on')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(['honor', 'medals', 'honor_exchanges', 'honor_exchanged_on']);
        });
    }
};

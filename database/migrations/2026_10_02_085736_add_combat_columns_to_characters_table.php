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
            $table->unsignedInteger('exp')->default(0)->after('level');
            // Null health/chakra means "full"; they regenerate from vitals_at.
            $table->unsignedInteger('hp')->nullable()->after('exp');
            $table->unsignedInteger('mp')->nullable()->after('hp');
            $table->timestamp('vitals_at')->nullable()->after('mp');
            // Highest Training Tower floor cleared.
            $table->unsignedSmallInteger('tower_floor')->default(0)->after('village');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(['exp', 'hp', 'mp', 'vitals_at', 'tower_floor']);
        });
    }
};

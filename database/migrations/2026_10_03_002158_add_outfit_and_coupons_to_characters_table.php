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
            // Gift coupons pay for Wishing Pots; new characters get config('game.starting_coupons').
            $table->unsignedInteger('coupons')->default(0)->after('gold');
            // Null wears the created avatar.
            $table->foreignId('outfit_id')->nullable()->after('avatar')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('outfit_id');
            $table->dropColumn('coupons');
        });
    }
};

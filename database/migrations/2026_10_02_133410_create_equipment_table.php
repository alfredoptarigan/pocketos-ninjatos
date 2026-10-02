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
        // The equipment catalogue, from the original equipitem table.
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            // Original item id, e.g. "i250101".
            $table->string('code', 16)->unique();
            $table->string('name', 64);
            $table->string('slot', 16)->index();
            $table->unsignedSmallInteger('level');
            $table->string('icon');
            $table->unsignedInteger('price');
            $table->unsignedInteger('min_attack')->default(0);
            $table->unsignedInteger('max_attack')->default(0);
            $table->unsignedInteger('defense')->default(0);
            $table->unsignedInteger('max_hp')->default(0);
            $table->unsignedSmallInteger('crit')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};

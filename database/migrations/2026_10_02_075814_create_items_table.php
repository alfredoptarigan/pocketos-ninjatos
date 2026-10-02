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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            // Original item id from the Pockie Ninja data tables, e.g. "i160006".
            $table->string('code', 16)->unique();
            $table->string('category', 16)->index();
            $table->string('name', 64);
            $table->string('icon');
            $table->unsignedInteger('price');
            $table->unsignedInteger('restore_hp')->default(0);
            $table->unsignedInteger('restore_chakra')->default(0);
            $table->unsignedInteger('restore_energy')->default(0);
            $table->unsignedSmallInteger('max_stack')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};

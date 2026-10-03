<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Where the ninja is: null for the village, "field:<scene>" or "dungeon:<id>".
        Schema::table('characters', function (Blueprint $table) {
            $table->string('location', 32)->nullable();
        });

        // Ninjas in the middle of a run are inside that dungeon.
        DB::table('dungeon_runs')->where('status', 'active')->get(['character_id', 'dungeon_id'])
            ->each(fn (object $run) => DB::table('characters')->where('id', $run->character_id)
                ->update(['location' => "dungeon:{$run->dungeon_id}"]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }
};

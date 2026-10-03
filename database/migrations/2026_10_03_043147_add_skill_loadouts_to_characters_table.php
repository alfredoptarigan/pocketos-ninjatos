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
        // Skill loadouts: three pages of equipped jutsu (slot => id), the page
        // in use, and slots bought beyond the free ones.
        Schema::table('characters', function (Blueprint $table) {
            $table->json('skill_pages')->nullable();
            $table->unsignedTinyInteger('skill_page')->default(0);
            $table->unsignedTinyInteger('skill_slots_bought')->default(0);
        });

        // Learned jutsu were a list; they become id => skill level, and the
        // first three go into the first page so ninjas keep fighting with them.
        DB::table('characters')->orderBy('id')->each(function (object $character) {
            $learned = json_decode($character->skills ?? '[]', true) ?: [];

            if (array_is_list($learned)) {
                DB::table('characters')->where('id', $character->id)->update([
                    'skills' => json_encode((object) array_fill_keys($learned, 1)),
                    'skill_pages' => json_encode([array_slice($learned, 0, 3), [], []]),
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('characters')->orderBy('id')->each(function (object $character) {
            $levels = json_decode($character->skills ?? '{}', true) ?: [];
            DB::table('characters')->where('id', $character->id)->update(['skills' => json_encode(array_map('strval', array_keys($levels)))]);
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(['skill_pages', 'skill_page', 'skill_slots_bought']);
        });
    }
};

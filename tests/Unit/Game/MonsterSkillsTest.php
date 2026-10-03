<?php

namespace Tests\Unit\Game;

use App\Game\MonsterSkills;
use App\Game\Skill;
use App\Models\TowerFloor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonsterSkillsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stronger_monsters_and_bosses_know_more_and_higher_jutsu()
    {
        $rules = config('skills.monsters');
        $weak = MonsterSkills::for('Rudbornn', 5, false);
        $strong = MonsterSkills::for('Rudbornn', 90, false);
        $boss = MonsterSkills::for('Aaroniero', 13, true);

        $this->assertCount($rules['base'], $weak);
        $this->assertCount(min($rules['max'], $rules['base'] + intdiv(90, $rules['per_levels'])), $strong);
        $this->assertCount($rules['base'] + $rules['boss_bonus'], $boss);
        $this->assertSame([1], array_values(array_unique(array_map(fn (Skill $skill) => $this->tier($skill), $weak))));
        $this->assertSame(1 + intdiv(90, $rules['skill_level_per_levels']), $strong[0]->level);
    }

    public function test_the_same_monster_always_knows_the_same_jutsu()
    {
        $ids = fn () => array_map(fn (Skill $skill) => $skill->id, MonsterSkills::for('Grimmjow', 20, true));

        $this->assertSame($ids(), $ids());
        $this->assertNotSame($ids(), array_map(fn (Skill $skill) => $skill->id, MonsterSkills::for('Luppi', 20, true)));
    }

    public function test_loadouts_never_hold_jutsu_that_exclude_each_other()
    {
        foreach (range(1, 100) as $level) {
            $ids = array_map(fn (Skill $skill) => $skill->id, MonsterSkills::for("Monster {$level}", $level, true));

            foreach ($ids as $id) {
                $this->assertNotContains(config("skills.skills.$id.excludes"), $ids);
            }
        }
    }

    public function test_opponents_fight_with_their_jutsu_and_chakra()
    {
        $floor = TowerFloor::factory()->create(['name' => 'Aaroniero', 'is_boss' => true, 'level' => 13, 'max_mp' => 206]);
        $fighter = $floor->combatant();

        $this->assertSame(206, $fighter->mp);
        $this->assertSame(206, $fighter->maxMp);
        $this->assertCount(3, $fighter->skills);
    }

    private function tier(Skill $skill): int
    {
        return config("skills.skills.{$skill->id}.tier");
    }
}

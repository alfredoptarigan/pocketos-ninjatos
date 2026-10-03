<?php

namespace App\Game;

use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * The jutsu an opponent fights with (config('skills.monsters')): a set drawn
 * once per monster name, so every Rudbornn knows the same jutsu.
 */
final class MonsterSkills
{
    /**
     * @return list<Skill> in trigger order
     */
    public static function for(string $name, int $level, bool $boss): array
    {
        $rules = config('skills.monsters');
        $count = min($rules['max'], $rules['base'] + intdiv($level, $rules['per_levels']) + ($boss ? $rules['boss_bonus'] : 0));
        $tier = min(4, 1 + intdiv($level, $rules['tier_per_levels']));
        $skillLevel = min(config('skills.max_level'), 1 + intdiv($level, $rules['skill_level_per_levels']));
        $passive = count(array_filter(config('skills.passive.levels'), fn (int $from) => $level >= $from));

        $candidates = array_keys(array_filter(config('skills.skills'), fn (array $skill) => $skill['tier'] <= $tier));
        $random = new Randomizer(new Xoshiro256StarStar(hash('sha256', $name, true)));
        $chosen = [];

        foreach ($random->shuffleArray($candidates) as $id) {
            $excludes = config("skills.skills.$id.excludes");

            if (count($chosen) < $count && ! in_array($excludes, $chosen, true)) {
                $chosen[] = (string) $id;
            }
        }

        return array_map(fn (string $id) => Skill::find($id, $skillLevel, $passive), $chosen);
    }
}

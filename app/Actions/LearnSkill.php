<?php

namespace App\Actions;

use App\Models\Character;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LearnSkill
{
    /**
     * Spend a skill point to learn a jutsu, or to raise a known one a level.
     * Learning needs the previous jutsu of its school.
     *
     * @return int the jutsu's new skill level
     *
     * @throws ValidationException when out of points, maxed, or missing the previous jutsu
     */
    public function handle(Character $character, string $skillId): int
    {
        return DB::transaction(function () use ($character, $skillId) {
            $ninja = Character::query()->lockForUpdate()->findOrFail($character->id);
            $skill = config("skills.skills.$skillId");
            $level = $ninja->skills[$skillId] ?? 0;
            $requires = $skill['requires'] ?? null;

            $problem = match (true) {
                $level >= config('skills.max_level') => "{$skill['name']} is already at the highest level.",
                $level === 0 && $requires !== null && ! isset($ninja->skills[$requires]) => 'Learn '.config("skills.skills.$requires.name").' first.',
                $ninja->skillPoints() < 1 => 'No skill points left. You get one every level.',
                default => null,
            };

            if ($problem !== null) {
                throw ValidationException::withMessages(['skill' => $problem]);
            }

            $ninja->forceFill(['skills' => array_replace($ninja->skills, [$skillId => $level + 1])])->save();

            return $level + 1;
        });
    }
}

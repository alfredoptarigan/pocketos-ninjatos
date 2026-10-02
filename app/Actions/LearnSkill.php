<?php

namespace App\Actions;

use App\Game\Skill;
use App\Models\Character;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LearnSkill
{
    /**
     * Pay for and learn a jutsu, if the ninja qualifies.
     *
     * @throws ValidationException when the ninja cannot learn it yet
     */
    public function handle(Character $character, Skill $skill): void
    {
        DB::transaction(function () use ($character, $skill) {
            $ninja = Character::query()->lockForUpdate()->findOrFail($character->id);
            $problem = match (true) {
                in_array($skill->id, $ninja->skills, true) => "You already know {$skill->name}.",
                $ninja->level < $skill->level => "{$skill->name} needs level {$skill->level}.",
                $skill->requires !== null && ! in_array($skill->requires, $ninja->skills, true) => "Learn {$this->nameOf($skill->requires)} first.",
                $ninja->gold < $skill->price() => "{$skill->name} costs {$skill->price()} gold.",
                default => null,
            };

            if ($problem !== null) {
                throw ValidationException::withMessages(['skill' => $problem]);
            }

            $ninja->forceFill([
                'gold' => $ninja->gold - $skill->price(),
                'skills' => [...$ninja->skills, $skill->id],
            ])->save();
        });
    }

    private function nameOf(string $skillId): string
    {
        return config("game.skills.$skillId.name");
    }
}

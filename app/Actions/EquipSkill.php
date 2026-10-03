<?php

namespace App\Actions;

use App\Models\Character;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EquipSkill
{
    /**
     * Put a learned jutsu in an open slot of the page in use (moving it if
     * it sits in another slot), or empty the slot when $skillId is null.
     *
     * @throws ValidationException for a locked slot, an unknown jutsu or one it excludes
     */
    public function handle(Character $character, ?string $skillId, int $slot): void
    {
        DB::transaction(function () use ($character, $skillId, $slot) {
            $ninja = Character::query()->lockForUpdate()->findOrFail($character->id);
            $page = $ninja->skill_pages[$ninja->skill_page] ?? [];

            if ($slot < 0 || $slot >= $ninja->openSkillSlots()) {
                throw ValidationException::withMessages(['slot' => 'That slot is locked.']);
            }

            if ($skillId !== null) {
                $this->ensureEquippable($ninja, $skillId, $page, $slot);
                $page = array_map(fn (?string $id) => $id === $skillId ? null : $id, $page);
            }

            $page = array_replace(array_fill(0, $slot + 1, null), $page, [$slot => $skillId]);
            // Trailing empty slots are dropped so pages stay short.
            while ($page !== [] && end($page) === null) {
                array_pop($page);
            }
            $ninja->forceFill(['skill_pages' => array_replace($ninja->skill_pages, [$ninja->skill_page => $page])])->save();
        });
    }

    /**
     * @param  list<string|null>  $page
     */
    private function ensureEquippable(Character $ninja, string $skillId, array $page, int $slot): void
    {
        if (! isset($ninja->skills[$skillId])) {
            throw ValidationException::withMessages(['skill' => 'Learn that jutsu first.']);
        }

        $excludes = config("skills.skills.$skillId.excludes");
        $others = array_diff_key($page, [$slot => true]);

        if ($excludes !== null && in_array($excludes, $others, true)) {
            $names = config("skills.skills.$skillId.name").' and '.config("skills.skills.$excludes.name");

            throw ValidationException::withMessages(['skill' => "{$names} cannot be equipped together."]);
        }
    }
}

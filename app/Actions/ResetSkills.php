<?php

namespace App\Actions;

use App\Models\Character;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResetSkills
{
    /**
     * Forget every jutsu and empty the loadouts, refunding all skill points,
     * for config('skills.reset_coupons') gift coupons.
     *
     * @throws ValidationException when short of coupons
     */
    public function handle(Character $character): void
    {
        DB::transaction(function () use ($character) {
            $ninja = Character::query()->lockForUpdate()->findOrFail($character->id);
            $price = config('skills.reset_coupons');

            if ($ninja->coupons < $price) {
                throw ValidationException::withMessages(['coupons' => "Resetting costs {$price} gift coupons."]);
            }

            $ninja->forceFill([
                'skills' => [],
                'skill_pages' => array_fill(0, config('skills.slots.pages'), []),
                'coupons' => $ninja->coupons - $price,
            ])->save();
        });
    }
}

<?php

namespace App\Actions;

use App\Models\Character;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BuySkillSlot
{
    /**
     * Open the next equipped slot for gift coupons (config('skills.slots.prices'), in order).
     *
     * @throws ValidationException when all slots are open or the ninja is short of coupons
     */
    public function handle(Character $character): void
    {
        DB::transaction(function () use ($character) {
            $ninja = Character::query()->lockForUpdate()->findOrFail($character->id);
            $price = config('skills.slots.prices')[$ninja->skill_slots_bought] ?? null;

            if ($price === null || $ninja->openSkillSlots() >= config('skills.slots.total')) {
                throw ValidationException::withMessages(['slot' => 'Every slot is already open.']);
            }

            if ($ninja->coupons < $price) {
                throw ValidationException::withMessages(['coupons' => "The next slot costs {$price} gift coupons."]);
            }

            $ninja->forceFill(['skill_slots_bought' => $ninja->skill_slots_bought + 1, 'coupons' => $ninja->coupons - $price])->save();
        });
    }
}

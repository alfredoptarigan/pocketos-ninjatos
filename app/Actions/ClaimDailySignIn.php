<?php

namespace App\Actions;

use App\Game\Leveling;
use App\Models\Character;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClaimDailySignIn
{
    /**
     * Claim today's sign-in reward and move the 7-day streak on.
     *
     * @return array{day: int, exp: int, coupons: int, levelUp: bool}
     *
     * @throws ValidationException when today's reward was already claimed
     */
    public function handle(Character $character): array
    {
        return DB::transaction(function () use ($character) {
            $ninja = Character::query()->lockForUpdate()->findOrFail($character->id);
            $last = $ninja->signed_in_on;

            if ($last?->isToday()) {
                throw ValidationException::withMessages(['sign_in' => 'You already signed in today. Come back tomorrow.']);
            }

            $config = config('game.sign_in');
            $continues = $last?->isYesterday() && $ninja->sign_in_streak < count($config['coupons']);
            $day = $continues ? $ninja->sign_in_streak + 1 : 1;
            $exp = intdiv(Leveling::expToNext($ninja->level) * $config['exp_percent'], 100);
            $coupons = $config['coupons'][$day - 1];
            [$level, $levelExp] = Leveling::gain($ninja->level, $ninja->exp, $exp);

            $ninja->forceFill([
                'level' => $level,
                'exp' => $levelExp,
                'coupons' => $ninja->coupons + $coupons,
                'sign_in_streak' => $day,
                'signed_in_on' => Carbon::today(),
            ])->save();

            return ['day' => $day, 'exp' => $exp, 'coupons' => $coupons, 'levelUp' => $level > $character->level];
        });
    }
}

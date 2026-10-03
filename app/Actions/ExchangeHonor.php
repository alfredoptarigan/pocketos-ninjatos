<?php

namespace App\Actions;

use App\Game\Leveling;
use App\Models\Character;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExchangeHonor
{
    /**
     * Trade medals for EXP at the ninja's honor rank (config('game.honor.exchange')).
     *
     * @return array{medals: int, exp: int, levelUp: bool}
     *
     * @throws ValidationException when out of exchanges today or short of medals
     */
    public function handle(Character $character): array
    {
        return DB::transaction(function () use ($character) {
            $ninja = Character::query()->lockForUpdate()->findOrFail($character->id);
            [$medals, $exp] = config('game.honor.exchange')[$ninja->honorRank() - 1];

            $error = match (true) {
                $ninja->honorExchangesLeft() < 1 => ['honor' => 'No Honor Exchanges left today. Come back tomorrow.'],
                $ninja->medals < $medals => ['medals' => "Not enough medals: an exchange costs {$medals}."],
                default => null,
            };

            if ($error !== null) {
                throw ValidationException::withMessages($error);
            }

            $made = $ninja->honor_exchanged_on?->isToday() ? $ninja->honor_exchanges : 0;
            [$level, $levelExp] = Leveling::gain($ninja->level, $ninja->exp, $exp);
            $ninja->forceFill([
                'level' => $level,
                'exp' => $levelExp,
                'medals' => $ninja->medals - $medals,
                'honor_exchanges' => $made + 1,
                'honor_exchanged_on' => Carbon::today(),
            ])->save();

            return ['medals' => $medals, 'exp' => $exp, 'levelUp' => $level > $character->level];
        });
    }
}

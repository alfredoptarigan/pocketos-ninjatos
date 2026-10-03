<?php

namespace App\Actions;

use App\Models\Character;
use App\Models\Outfit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DrawWishPot
{
    /**
     * Pay for one Wishing Pot and draw an outfit: a rarity by the pot's
     * weights, then any outfit of that rarity for the ninja's sex.
     * A duplicate pays config('game.outfits.duplicate_gold') instead.
     *
     * @param  array{name: string, price: int, odds: array<string, int>}  $pot
     * @return array{outfit: Outfit, duplicate: bool, gold: int}
     *
     * @throws ValidationException when the ninja is short of coupons
     */
    public function handle(Character $character, array $pot): array
    {
        return DB::transaction(function () use ($character, $pot) {
            $ninja = Character::query()->lockForUpdate()->findOrFail($character->id);

            if ($ninja->coupons < $pot['price']) {
                throw ValidationException::withMessages([
                    'coupons' => "Not enough gift coupons: {$pot['name']} costs {$pot['price']} but you have {$ninja->coupons}.",
                ]);
            }

            $outfit = Outfit::query()->inPots()
                ->where('sex', $ninja->sex())
                ->where('rarity', $this->rollRarity($pot['odds']))
                ->inRandomOrder()
                ->firstOrFail();
            $duplicate = $ninja->outfits()->whereKey($outfit->id)->exists();
            $gold = $duplicate ? config("game.outfits.duplicate_gold.{$outfit->rarity}") : 0;

            if (! $duplicate) {
                $ninja->outfits()->attach($outfit);
            }
            $ninja->forceFill(['coupons' => $ninja->coupons - $pot['price'], 'gold' => $ninja->gold + $gold])->save();

            return ['outfit' => $outfit, 'duplicate' => $duplicate, 'gold' => $gold];
        });
    }

    /**
     * @param  array<string, int>  $odds  rarity => weight
     */
    private function rollRarity(array $odds): string
    {
        $roll = random_int(1, array_sum($odds));

        foreach ($odds as $rarity => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                return $rarity;
            }
        }

        return array_key_last($odds);
    }
}

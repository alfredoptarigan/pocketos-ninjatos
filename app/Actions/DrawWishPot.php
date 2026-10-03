<?php

namespace App\Actions;

use App\Models\Character;
use App\Models\Outfit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DrawWishPot
{
    /**
     * Pay for one Wishing Pot and get an outfit. Random pots roll a rarity by
     * their weights, then any outfit of that rarity for the ninja's sex; a
     * duplicate pays config('game.outfits.duplicate_gold') instead. Pick pots
     * give the chosen outfit from their list.
     *
     * @param  array{name: string, price: int, odds?: array<string, int>, pick?: list<string>}  $pot
     * @return array{outfit: Outfit, duplicate: bool, gold: int}
     *
     * @throws ValidationException when the ninja is short of coupons or picked badly
     */
    public function handle(Character $character, array $pot, ?string $choice = null): array
    {
        return DB::transaction(function () use ($character, $pot, $choice) {
            $ninja = Character::query()->lockForUpdate()->findOrFail($character->id);

            if ($ninja->coupons < $pot['price']) {
                throw ValidationException::withMessages([
                    'coupons' => "Not enough gift coupons: {$pot['name']} costs {$pot['price']} but you have {$ninja->coupons}.",
                ]);
            }

            $outfit = isset($pot['pick'])
                ? $this->picked($ninja, $pot['pick'], $choice)
                : Outfit::query()->inPots()
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
     * The chosen outfit of a pick pot: on its list, for the ninja's sex, not owned yet.
     *
     * @param  list<string>  $keys
     */
    private function picked(Character $ninja, array $keys, ?string $choice): Outfit
    {
        $outfit = in_array($choice, $keys, true)
            ? Outfit::query()->where('key', $choice)->where('sex', $ninja->sex())->first()
            : null;

        if ($outfit === null) {
            throw ValidationException::withMessages(['outfit' => 'Pick one of the outfits this pot offers.']);
        }

        if ($ninja->outfits()->whereKey($outfit->id)->exists()) {
            throw ValidationException::withMessages(['outfit' => "You already own {$outfit->name}."]);
        }

        return $outfit;
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

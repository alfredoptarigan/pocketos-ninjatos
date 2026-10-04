<?php

namespace App\Actions;

use App\Models\Character;
use App\Models\Outfit;
use App\Models\Title;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DrawWishPot
{
    /**
     * Pay for one Wishing Pot (config('game.outfits.pots')) and get what it
     * gives. Random pots roll a rarity by their weights, then any outfit of
     * that rarity for the ninja's sex; pool pots draw from their list; pick
     * pots give the chosen outfit. A pot with a 'level' gives the outfit at
     * that upgrade, raising an owned one; an outfit the ninja already has at
     * that level gives config('game.outfits.duplicate_shards') instead. Title
     * boxes grant one of their titles not owned yet.
     *
     * @param  array<string, mixed>  $pot
     * @return array{outfit?: Outfit, level?: int, duplicate?: bool, shards?: int, title?: Title}
     *
     * @throws ValidationException when the pot is locked, sold out, too dear or picked badly
     */
    public function handle(Character $character, array $pot, ?string $choice = null): array
    {
        if (isset($pot['locked'])) {
            throw ValidationException::withMessages(['pot' => "{$pot['name']}: {$pot['locked']}."]);
        }

        return DB::transaction(function () use ($character, $pot, $choice) {
            $ninja = Character::query()->lockForUpdate()->findOrFail($character->id);

            if ($ninja->coupons < $pot['price']) {
                throw ValidationException::withMessages([
                    'coupons' => "Not enough gift coupons: {$pot['name']} costs {$pot['price']} but you have {$ninja->coupons}.",
                ]);
            }

            $result = isset($pot['titles']) ? $this->title($ninja, $pot) : $this->outfit($ninja, $pot, $choice);
            $ninja->forceFill([
                'coupons' => $ninja->coupons - $pot['price'],
                'outfit_shards' => $ninja->outfit_shards + ($result['shards'] ?? 0),
            ])->save();

            return $result;
        });
    }

    /**
     * @param  array<string, mixed>  $pot
     * @return array{outfit: Outfit, level: int, duplicate: bool, shards: int}
     */
    private function outfit(Character $ninja, array $pot, ?string $choice): array
    {
        $level = $pot['level'] ?? 0;
        $outfit = match (true) {
            isset($pot['pick']) => $this->picked($ninja, $pot['pick'], $choice, $level),
            isset($pot['pool']) => Outfit::query()->whereIn('key', $pot['pool'])->where('sex', $ninja->sex())->inRandomOrder()->firstOrFail(),
            default => Outfit::query()->inPots()
                ->where('sex', $ninja->sex())
                ->where('rarity', $this->rollRarity($pot['odds']))
                ->inRandomOrder()
                ->firstOrFail(),
        };
        $owned = $this->ownedLevel($ninja, $outfit);
        $duplicate = $owned !== null && $owned >= $level;

        if ($owned === null) {
            $ninja->outfits()->attach($outfit, ['level' => $level]);
        } elseif (! $duplicate) {
            $ninja->outfits()->updateExistingPivot($outfit->id, ['level' => $level]);
        }

        return [
            'outfit' => $outfit,
            'level' => max($level, $owned ?? 0),
            'duplicate' => $duplicate,
            'shards' => $duplicate ? config('game.outfits.duplicate_shards')[$outfit->rarity] : 0,
        ];
    }

    /**
     * The chosen outfit of a pick pot: on its list, for the ninja's sex, and
     * not owned at the pot's level already.
     *
     * @param  list<string>  $keys
     */
    private function picked(Character $ninja, array $keys, ?string $choice, int $level): Outfit
    {
        $outfit = in_array($choice, $keys, true)
            ? Outfit::query()->where('key', $choice)->where('sex', $ninja->sex())->first()
            : null;

        if ($outfit === null) {
            throw ValidationException::withMessages(['outfit' => 'Pick one of the outfits this pot offers.']);
        }

        $owned = $this->ownedLevel($ninja, $outfit);

        if ($owned !== null && $owned >= $level) {
            throw ValidationException::withMessages(['outfit' => $level > 0 ? "Your {$outfit->name} is already +{$owned}." : "You already own {$outfit->name}."]);
        }

        return $outfit;
    }

    /**
     * @param  array<string, mixed>  $pot
     * @return array{title: Title}
     */
    private function title(Character $ninja, array $pot): array
    {
        $title = Title::query()->whereIn('code', $pot['titles'])
            ->whereNotIn('id', $ninja->titles()->select('titles.id'))
            ->inRandomOrder()
            ->first();

        if ($title === null) {
            throw ValidationException::withMessages(['pot' => "You already own every title of the {$pot['name']}."]);
        }

        $ninja->titles()->attach($title);

        return ['title' => $title];
    }

    private function ownedLevel(Character $ninja, Outfit $outfit): ?int
    {
        $level = $ninja->outfits()->whereKey($outfit->id)->value('character_outfits.level');

        return $level === null ? null : (int) $level;
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

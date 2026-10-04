<?php

namespace App\Console\Commands;

use App\Models\Character;
use App\Models\Equipment;
use App\Models\Item;
use App\Models\Outfit;
use App\Models\Title;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A local QA ninja to try every feature: max level, plenty of gold, gift
 * coupons, shards and medals, every outfit of its sex, one of every piece of
 * gear, a full stack of every item, and every title. Running it again refreshes the same account.
 * Never in production: it hands out everything.
 */
#[Signature('game:qa-account
    {--email=qa@pocketo.test : Login email}
    {--password= : Login password (a random one is printed when left out)}
    {--avatar=0_3 : Created avatar, which also sets the sex of the outfits given}')]
#[Description('Create or refresh a maxed-out QA account to try every feature (local only)')]
class QaAccount extends Command
{
    private const GOLD = 10_000_000;

    private const COUPONS = 100_000;

    private const OUTFIT_SHARDS = 10_000;

    private const MEDALS = 100_000;

    public function handle(): int
    {
        if ($this->laravel->isProduction()) {
            $this->error('The QA account hands out everything: it is not available in production.');

            return self::FAILURE;
        }

        $avatar = (string) $this->option('avatar');
        if (! array_key_exists($avatar, config('game.avatars'))) {
            $this->error("Unknown avatar {$avatar}; pick one of: ".implode(', ', array_keys(config('game.avatars'))));

            return self::FAILURE;
        }

        $email = (string) $this->option('email');
        $password = $this->option('password') ?: Str::password(16, symbols: false);

        DB::transaction(function () use ($email, $password, $avatar) {
            $user = User::query()->updateOrCreate(['email' => $email], ['name' => 'QA', 'password' => $password]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $ninja = $user->character ?? $user->character()->create(['name' => 'QA Ninja', 'avatar' => $avatar]);
            $this->fillUp($ninja->refresh());
        });

        $this->info('QA account ready.');
        $this->table(['Email', 'Password'], [[$email, $password]]);

        return self::SUCCESS;
    }

    private function fillUp(Character $ninja): void
    {
        $ninja->forceFill([
            'level' => config('game.combat.max_level'),
            'exp' => 0,
            'hp' => null,
            'mp' => null,
            'gold' => self::GOLD,
            'coupons' => self::COUPONS,
            'outfit_shards' => self::OUTFIT_SHARDS,
            'medals' => self::MEDALS,
        ])->save();

        $ninja->outfits()->syncWithoutDetaching(Outfit::query()->where('sex', $ninja->sex())->pluck('id'));
        $ninja->titles()->syncWithoutDetaching(Title::query()->pluck('id'));

        $owned = $ninja->gear()->pluck('equipment_id');
        Equipment::query()->whereNotIn('id', $owned)->pluck('id')
            ->each(fn (int $id) => $ninja->gear()->forceCreate(['equipment_id' => $id]));

        // A full stack of every item.
        Item::query()->get(['id', 'max_stack'])->each(fn (Item $item) => $ninja->inventory()->updateOrCreate(
            ['item_id' => $item->id],
            ['quantity' => $item->max_stack],
        ));
    }
}

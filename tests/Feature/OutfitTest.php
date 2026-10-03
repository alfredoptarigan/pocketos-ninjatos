<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Equipment;
use App\Models\Outfit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OutfitTest extends TestCase
{
    use RefreshDatabase;

    private function ninja(array $overrides = []): Character
    {
        return Character::factory()->create(['avatar' => '0_3', 'coupons' => 100, 'gold' => 0, ...$overrides]);
    }

    public function test_players_need_a_character_first()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('wish-pot.show'))->assertRedirect(route('character.create'));
        $this->get(route('outfits.index'))->assertRedirect(route('character.create'));
    }

    public function test_wish_pot_lists_the_pots_and_the_coupons()
    {
        $character = $this->ninja(['coupons' => 7]);
        $this->actingAs($character->user);

        $this->get(route('wish-pot.show'))->assertInertia(fn (Assert $page) => $page
            ->component('buildings/wish-pot')
            ->has('pots', count(config('game.outfits.pots')))
            ->where('pots.0.key', 'ninja')
            ->where('pots.0.odds.orange', 5)
            ->where('character.coupons', 7));
    }

    public function test_a_pot_draws_an_outfit_of_its_rarity_for_the_ninjas_sex()
    {
        $character = $this->ninja();
        $kakashi = Outfit::factory()->create(['key' => '0_47', 'sex' => 0, 'rarity' => 'orange']);
        Outfit::factory()->create(['key' => '0_98', 'sex' => 0, 'rarity' => 'orange']); // event only
        Outfit::factory()->create(['key' => '1_72', 'sex' => 1, 'rarity' => 'orange']);
        Outfit::factory()->create(['key' => '0_21', 'sex' => 0, 'rarity' => 'blue']);
        $this->actingAs($character->user);

        $this->post(route('wish-pot.draw', 'orange'))->assertRedirect(route('wish-pot.show'));

        $character->refresh();
        $this->assertSame(100 - config('game.outfits.pots.orange.price'), $character->coupons);
        $this->assertSame([$kakashi->id], $character->outfits()->pluck('outfits.id')->all());
    }

    public function test_a_duplicate_draw_gives_outfit_shards_instead()
    {
        $character = $this->ninja();
        $outfit = Outfit::factory()->create(['key' => '0_21', 'sex' => 0, 'rarity' => 'blue']);
        $character->outfits()->attach($outfit);
        $this->actingAs($character->user);

        $this->post(route('wish-pot.draw', 'blue'));

        $character->refresh();
        $this->assertSame(1, $character->outfits()->count());
        $this->assertSame(config('game.outfits.duplicate_shards.blue'), $character->outfit_shards);
    }

    public function test_a_pot_needs_enough_coupons()
    {
        $character = $this->ninja(['coupons' => 4]);
        Outfit::factory()->create(['key' => '0_3', 'sex' => 0, 'rarity' => 'grey']);
        $this->actingAs($character->user);

        $this->post(route('wish-pot.draw', 'grey'))->assertSessionHasErrors('coupons');

        $this->assertSame(4, $character->refresh()->coupons);
        $this->assertSame(0, $character->outfits()->count());
    }

    public function test_unknown_pots_are_not_found()
    {
        $this->actingAs($this->ninja()->user);

        $this->post(route('wish-pot.draw', 'golden'))->assertNotFound();
        $this->post(route('wish-pot.draw', 'ninja.odds'))->assertNotFound();
    }

    public function test_a_pick_pot_gives_the_chosen_outfit()
    {
        $character = $this->ninja(['coupons' => 200]);
        $hokage = Outfit::factory()->create(['key' => '0_53', 'sex' => 0, 'rarity' => 'orange']);
        $this->actingAs($character->user);

        $this->get(route('wish-pot.show'))->assertInertia(fn (Assert $page) => $page
            ->where('pots.5.key', 's_rank')
            ->has('pots.5.choices', 1)
            ->where('pots.5.choices.0.key', '0_53'));

        $this->post(route('wish-pot.draw', 's_rank'), ['outfit' => '0_53'])->assertRedirect(route('wish-pot.show'));

        $this->assertSame([$hokage->id], $character->outfits()->pluck('outfits.id')->all());
        $this->assertSame(200 - config('game.outfits.pots.s_rank.price'), $character->refresh()->coupons);
    }

    public function test_a_pick_pot_refuses_other_choices_and_outfits_already_owned()
    {
        $character = $this->ninja(['coupons' => 200]);
        $owned = Outfit::factory()->create(['key' => '0_53', 'sex' => 0]);
        Outfit::factory()->create(['key' => '1_44', 'sex' => 1]);
        Outfit::factory()->create(['key' => '0_47', 'sex' => 0]);
        $character->outfits()->attach($owned);
        $this->actingAs($character->user);

        $this->post(route('wish-pot.draw', 's_rank'), ['outfit' => '1_44'])->assertSessionHasErrors('outfit'); // other sex
        $this->post(route('wish-pot.draw', 's_rank'), ['outfit' => '0_47'])->assertSessionHasErrors('outfit'); // not in this pot
        $this->post(route('wish-pot.draw', 's_rank'), ['outfit' => '0_53'])->assertSessionHasErrors('outfit'); // owned

        $this->assertSame(200, $character->refresh()->coupons);
    }

    public function test_wearing_an_outfit_changes_the_look_and_adds_its_bonus()
    {
        $character = $this->ninja(['level' => 1]);
        $outfit = Outfit::factory()->create(['key' => '0_47', 'sex' => 0, 'rarity' => 'orange']);
        $character->outfits()->attach($outfit);
        $plain = $character->stats();
        $this->actingAs($character->user);

        $this->post(route('outfits.wear', $outfit))->assertRedirect(route('outfits.index'));

        $character->refresh();
        $this->assertSame('0_47', $character->hud()['avatar']);
        $this->assertSame((int) round($plain->maxHp * 1.1), $character->stats()->maxHp);
        $this->assertSame((int) round($plain->minAttack * 1.1), $character->stats()->minAttack);

        $this->get(route('outfits.index'))->assertInertia(fn (Assert $page) => $page
            ->component('character/outfits')
            ->has('outfits', 1)
            ->where('outfits.0.key', '0_47')
            ->where('worn', $outfit->id));

        // Outfits have no create-screen portrait; the panel draws their sprite.
        $this->get(route('bag'))->assertInertia(fn (Assert $page) => $page->where('hasPortrait', false));

        $this->post(route('outfits.take-off'))->assertRedirect(route('outfits.index'));
        $this->assertSame('0_3', $character->refresh()->hud()['avatar']);
    }

    public function test_an_outfit_of_another_class_sends_the_weapon_back_to_the_bag()
    {
        $character = $this->ninja(); // a Fists avatar
        $claw = $character->gear()->forceCreate([
            'equipment_id' => Equipment::factory()->create(['look' => 'gloves1'])->id, 'equipped_slot' => 'weapon',
        ]);
        $every = Outfit::factory()->create(['key' => '0_87', 'weapon_class' => null]);
        $swordsman = Outfit::factory()->create(['key' => '0_1', 'weapon_class' => 'sharp']);
        $character->outfits()->attach([$every->id, $swordsman->id]);
        $this->actingAs($character->user);

        $this->post(route('outfits.wear', $every));
        $this->assertSame('weapon', $claw->refresh()->equipped_slot);

        $this->post(route('outfits.wear', $swordsman));
        $this->assertNull($claw->refresh()->equipped_slot);
        $this->assertSame('sharp', $character->refresh()->weaponClass());
    }

    public function test_outfits_the_ninja_does_not_own_cannot_be_worn()
    {
        $character = $this->ninja();
        $outfit = Outfit::factory()->create(['key' => '0_47', 'sex' => 0]);
        $this->actingAs($character->user);

        $this->post(route('outfits.wear', $outfit))->assertNotFound();
        $this->assertNull($character->refresh()->outfit_id);
    }

    public function test_upgrading_an_outfit_raises_its_level_and_bonus()
    {
        $character = $this->ninja(['level' => 10, 'gold' => 5000, 'outfit_shards' => 10]);
        $plain = $character->stats();
        $outfit = Outfit::factory()->create(['key' => '0_47', 'sex' => 0, 'rarity' => 'orange']);
        $character->outfits()->attach($outfit);
        $character->forceFill(['outfit_id' => $outfit->id])->save();
        $this->actingAs($character->user);

        $this->post(route('outfits.upgrade', $outfit))->assertRedirect(route('outfits.index'));
        $this->post(route('outfits.upgrade', $outfit))->assertRedirect(route('outfits.index'));

        $character->refresh();
        $upgrade = config('game.outfits.upgrade');
        $this->assertSame(2, $character->outfitLevel($outfit));
        $this->assertSame(5000 - 3 * $upgrade['gold'], $character->gold);
        $this->assertSame(10 - 3 * $upgrade['shards'], $character->outfit_shards);
        $percent = 100 + 10 + 2 * $upgrade['bonus_per_level'];
        $this->assertSame((int) round($plain->maxHp * $percent / 100), $character->stats()->maxHp);

        $this->actingAs($character->user->fresh())->get(route('outfits.index'))->assertInertia(fn (Assert $page) => $page
            ->where('outfits.0.level', 2)
            ->where('outfits.0.bonus', 10 + 2 * $upgrade['bonus_per_level'])
            ->where('outfits.0.upgrade.gold', 3 * $upgrade['gold'])
            ->where('shards', 10 - 3 * $upgrade['shards']));
    }

    public function test_upgrading_needs_gold_shards_and_the_character_level()
    {
        $outfit = Outfit::factory()->create(['key' => '0_47', 'sex' => 0]);
        $upgrade = config('game.outfits.upgrade');

        $poor = $this->ninja(['level' => 80, 'gold' => 0, 'outfit_shards' => 99]);
        $poor->outfits()->attach($outfit);
        $this->actingAs($poor->user)->post(route('outfits.upgrade', $outfit))->assertSessionHasErrors('gold');

        $noShards = $this->ninja(['level' => 80, 'gold' => 99999, 'outfit_shards' => 0]);
        $noShards->outfits()->attach($outfit);
        $this->actingAs($noShards->user)->post(route('outfits.upgrade', $outfit))->assertSessionHasErrors('shards');

        // +2 needs character level 3 (the original UseLevel steps of 3).
        $young = $this->ninja(['level' => 2, 'gold' => 99999, 'outfit_shards' => 99]);
        $young->outfits()->attach($outfit, ['level' => 1]);
        $this->actingAs($young->user)->post(route('outfits.upgrade', $outfit))->assertSessionHasErrors('level');

        $maxed = $this->ninja(['level' => 99, 'gold' => 999999, 'outfit_shards' => 999]);
        $maxed->outfits()->attach($outfit, ['level' => $upgrade['max_level']]);
        $this->actingAs($maxed->user)->post(route('outfits.upgrade', $outfit))->assertSessionHasErrors('outfit');

        $this->assertSame(0, $poor->outfitLevel($outfit));
        $this->assertSame(0, $noShards->outfitLevel($outfit));
        $this->assertSame(1, $young->outfitLevel($outfit));
    }

    public function test_outfits_the_ninja_does_not_own_cannot_be_upgraded()
    {
        $character = $this->ninja(['gold' => 99999, 'outfit_shards' => 99]);
        $outfit = Outfit::factory()->create(['key' => '0_47', 'sex' => 0]);

        $this->actingAs($character->user)->post(route('outfits.upgrade', $outfit))->assertNotFound();
    }
}

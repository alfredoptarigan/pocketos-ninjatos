<?php

namespace Tests\Feature;

use App\Models\Character;
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

    public function test_a_duplicate_draw_pays_gold_instead()
    {
        $character = $this->ninja();
        $outfit = Outfit::factory()->create(['key' => '0_21', 'sex' => 0, 'rarity' => 'blue']);
        $character->outfits()->attach($outfit);
        $this->actingAs($character->user);

        $this->post(route('wish-pot.draw', 'blue'));

        $character->refresh();
        $this->assertSame(1, $character->outfits()->count());
        $this->assertSame(config('game.outfits.duplicate_gold.blue'), $character->gold);
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

    public function test_outfits_the_ninja_does_not_own_cannot_be_worn()
    {
        $character = $this->ninja();
        $outfit = Outfit::factory()->create(['key' => '0_47', 'sex' => 0]);
        $this->actingAs($character->user);

        $this->post(route('outfits.wear', $outfit))->assertNotFound();
        $this->assertNull($character->refresh()->outfit_id);
    }
}

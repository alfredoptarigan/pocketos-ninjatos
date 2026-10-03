<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Outfit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CollectionTest extends TestCase
{
    use RefreshDatabase;

    private const ATTRIBUTES = ['strength' => 2, 'agility' => 20, 'stamina' => 4];

    private function ninja(array $overrides = []): Character
    {
        return Character::factory()->create(['avatar' => '0_3', 'level' => 30, ...$overrides]);
    }

    private function collectible(string $key, string $rarity = 'orange'): Outfit
    {
        return Outfit::factory()->create(['key' => $key, 'sex' => 0, 'rarity' => $rarity, 'collection' => self::ATTRIBUTES]);
    }

    public function test_the_collection_page_lists_collectible_outfits_of_the_ninjas_sex()
    {
        $character = $this->ninja();
        $owned = $this->collectible('0_47')->fill(['name' => 'Hatake Kakashi']);
        $owned->save();
        $this->collectible('0_53')->fill(['name' => 'Third Hokage'])->save();
        Outfit::factory()->create(['key' => '1_72', 'sex' => 1, 'collection' => self::ATTRIBUTES]);
        Outfit::factory()->create(['key' => '0_98', 'sex' => 0, 'collection' => null]);
        $character->outfits()->attach($owned, ['level' => 2]);

        $this->actingAs($character->user)->get(route('collection.index'))->assertInertia(fn (Assert $page) => $page
            ->component('character/collection')
            ->has('outfits', 2)
            ->where('outfits.0.key', '0_47')
            ->where('outfits.0.owned', true)
            ->where('outfits.0.level', 2)
            ->where('outfits.0.recorded', false)
            ->where('outfits.1.owned', false)
            ->where('tiers.orange.recorded', 0));
    }

    public function test_recording_a_plus_two_outfit_adds_its_attributes()
    {
        $character = $this->ninja();
        $outfit = $this->collectible('0_47');
        $character->outfits()->attach($outfit, ['level' => 2]);
        $plain = $character->stats();

        $this->actingAs($character->user)->post(route('collection.record', $outfit))->assertRedirect(route('collection.index'));

        $rules = config('game.attributes');
        $stats = $character->refresh()->stats();
        $this->assertSame($plain->minAttack + 2 * $rules['strength_attack'], $stats->minAttack);
        $this->assertSame($plain->maxHp + 4 * $rules['stamina_hp'], $stats->maxHp);
        $this->assertSame($plain->dodge + intdiv(20, $rules['agility_per_dodge']), $stats->dodge);
        // The outfit stays in the wardrobe.
        $this->assertSame(1, $character->outfits()->count());
    }

    public function test_recording_needs_a_plus_two_owned_collectible_outfit_not_yet_recorded()
    {
        $character = $this->ninja();
        $low = $this->collectible('0_47');
        $other = Outfit::factory()->create(['key' => '0_98', 'sex' => 0, 'collection' => null]);
        $notOwned = $this->collectible('0_53');
        $character->outfits()->attach($low, ['level' => 1]);
        $character->outfits()->attach($other, ['level' => 5]);
        $this->actingAs($character->user);

        $this->post(route('collection.record', $low))->assertSessionHasErrors('outfit');
        $this->post(route('collection.record', $other))->assertSessionHasErrors('outfit');
        $this->post(route('collection.record', $notOwned))->assertNotFound();

        $character->outfits()->updateExistingPivot($low->id, ['level' => 2]);
        $this->post(route('collection.record', $low))->assertSessionHasNoErrors();
        $this->post(route('collection.record', $low))->assertSessionHasErrors('outfit');
    }

    public function test_five_recorded_outfits_of_a_rarity_unlock_a_tier_at_its_character_level()
    {
        $character = $this->ninja(['level' => 24]);
        foreach (['0_1', '0_4', '0_5', '0_8', '0_27'] as $key) {
            $character->outfits()->attach($this->collectible($key), ['level' => 2, 'recorded_at' => now()]);
        }
        $rules = config('game.attributes');
        $recordedHp = 5 * 4 * $rules['stamina_hp'];
        $this->assertSame($this->ninja(['level' => 24])->stats()->maxHp + $recordedHp, $character->stats()->maxHp);

        $character->forceFill(['level' => 25])->save();
        $base = $this->ninja(['level' => 25])->stats();
        $percent = config('game.collection.tiers.orange.0.2');
        $flatHp = $base->maxHp + $recordedHp;
        $this->assertSame((int) round($flatHp * (100 + $percent) / 100), $character->stats()->maxHp);

        $this->actingAs($character->user)->get(route('collection.index'))->assertInertia(fn (Assert $page) => $page
            ->where('tiers.orange.recorded', 5)
            ->where('tiers.orange.tier', 1)
            ->where('tiers.orange.percent', $percent));
    }
}

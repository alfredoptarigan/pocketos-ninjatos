<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Outfit;
use App\Models\Title;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TitleTest extends TestCase
{
    use RefreshDatabase;

    private function title(int $id, string $code, array $bonus = []): Title
    {
        return Title::query()->create(['id' => $id, 'code' => $code, 'name' => "Title {$id}", 'category' => 1, 'bonus' => $bonus]);
    }

    private function ninja(): Character
    {
        return Character::factory()->create(['avatar' => '0_3', 'level' => 30]);
    }

    public function test_wearing_an_owned_title_adds_its_bonus_and_shows_in_the_hud()
    {
        $character = $this->ninja();
        $title = $this->title(1, 'EffortTitle01', ['stamina' => 13, 'attackPercent' => 5]);
        $character->grantTitle('EffortTitle01');
        $plain = $character->stats();
        $this->actingAs($character->user);

        $this->post(route('titles.wear', $title))->assertRedirect(route('titles.index'));

        $character->refresh();
        $this->assertSame($plain->maxHp + 13 * config('game.attributes.stamina_hp'), $character->stats()->maxHp);
        $this->assertSame((int) round($plain->minAttack * 1.05), $character->stats()->minAttack);
        $this->assertSame('Title 1', $character->hud()['title']);

        $this->post(route('titles.take-off'))->assertRedirect(route('titles.index'));
        $this->assertNull($character->refresh()->hud()['title']);
        $this->assertEquals($plain, $character->stats());
    }

    public function test_titles_the_ninja_does_not_own_cannot_be_worn()
    {
        $character = $this->ninja();
        $title = $this->title(1, 'EffortTitle01');

        $this->actingAs($character->user)->post(route('titles.wear', $title))->assertNotFound();
        $this->assertNull($character->refresh()->title_id);
    }

    public function test_granting_a_title_twice_keeps_one()
    {
        $character = $this->ninja();
        $this->title(1, 'EffortTitle01');

        $this->assertTrue($character->grantTitle('EffortTitle01'));
        $this->assertFalse($character->grantTitle('EffortTitle01'));
        $this->assertFalse($character->grantTitle('Unknown'));
        $this->assertSame(1, $character->titles()->count());
    }

    public function test_the_titles_page_lists_owned_titles_and_how_to_earn_the_others()
    {
        $character = $this->ninja();
        $this->title(1, 'EffortTitle01');
        $this->title(13, 'AvatarTitle101');
        $this->title(30, 'EffortTitle15'); // no source in this rework: hidden
        $character->grantTitle('EffortTitle01');

        $this->actingAs($character->user)->get(route('titles.index'))->assertInertia(fn (Assert $page) => $page
            ->component('character/titles')
            ->has('owned', 1)
            ->where('owned.0.code', 'EffortTitle01')
            ->has('locked', 1)
            ->where('locked.0.code', 'AvatarTitle101')
            ->where('locked.0.how', 'Record 5 orange outfits in the avatar collection.'));
    }

    public function test_recording_the_fifth_outfit_of_a_rarity_grants_its_collection_title()
    {
        $character = $this->ninja();
        $this->title(13, 'AvatarTitle101');
        $keys = ['0_1', '0_4', '0_5', '0_8', '0_27'];
        foreach ($keys as $key) {
            $outfit = Outfit::factory()->create(['key' => $key, 'sex' => 0, 'rarity' => 'orange', 'collection' => ['strength' => 1, 'agility' => 1, 'stamina' => 1]]);
            $character->outfits()->attach($outfit, ['level' => 2]);
        }
        $this->actingAs($character->user);

        foreach (Outfit::query()->get() as $outfit) {
            $this->assertSame(0, $character->titles()->count());
            $this->post(route('collection.record', $outfit));
        }

        $this->assertSame(['AvatarTitle101'], $character->titles()->pluck('code')->all());
    }
}

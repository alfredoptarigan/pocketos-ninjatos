<?php

namespace Tests\Feature;

use App\Models\Battle;
use App\Models\Character;
use App\Models\Equipment;
use App\Models\TowerFloor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Lottery;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TowerTest extends TestCase
{
    use RefreshDatabase;

    private function weakFloor(int $floor, array $overrides = []): TowerFloor
    {
        return TowerFloor::factory()->create(['floor' => $floor, 'max_hp' => 1, 'dodge' => 0, 'priority' => 0, 'exp' => 100, ...$overrides]);
    }

    private function deadlyFloor(int $floor): TowerFloor
    {
        return TowerFloor::factory()->create([
            'floor' => $floor, 'max_hp' => 100000, 'min_atk' => 100000, 'max_atk' => 100000, 'priority' => 100, 'dodge' => 0,
        ]);
    }

    public function test_players_need_a_character_first()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('tower.show'))->assertRedirect(route('character.create'));
    }

    public function test_the_battle_log_shows_the_weapon_the_ninja_holds()
    {
        $character = Character::factory()->create();
        $sword = Equipment::factory()->create(['look' => 'sharp20']);
        $character->gear()->forceCreate(['equipment_id' => $sword->id, 'equipped_slot' => 'weapon']);
        $this->weakFloor(1);
        $this->actingAs($character->user);

        $this->post(route('tower.fight', 1));
        $character->gear()->update(['equipped_slot' => null]);
        $this->post(route('tower.fight', 1));

        [$armed, $bare] = Battle::orderBy('id')->get();
        $this->assertSame('sharp20', $armed->log['fighters'][0]['weapon']);
        $this->assertNull($bare->log['fighters'][0]['weapon']);
    }

    public function test_ninjas_and_avatar_bosses_bring_their_outfits_ultimate()
    {
        $character = Character::factory()->create(['avatar' => '0_3']);
        $this->weakFloor(1, ['art' => ['type' => 'motion', 'motions' => '', 'face' => '', 'outfit' => '0_10', 'outfit_level' => 1]]);
        $this->actingAs($character->user);

        $this->post(route('tower.fight', 1));

        $fighters = Battle::sole()->log['fighters'];
        $this->assertSame('1903', $fighters[0]['ultimate']);
        $this->assertSame('1910', $fighters[1]['ultimate']);

        // The replay names and shows both sides' ultimates like any jutsu.
        $this->get(route('battles.show', Battle::sole()))->assertInertia(fn (Assert $page) => $page
            ->where('skills.1903.name', 'Secret Technique')
            ->where('skills.1910.icon', '/game-assets/skills/1910.png'));
    }

    public function test_tower_lists_the_floors_and_the_players_progress()
    {
        $character = Character::factory()->create(['tower_floor' => 1]);
        $this->weakFloor(1);
        $this->weakFloor(2);
        $this->actingAs($character->user);

        $this->get(route('tower.show'))->assertInertia(fn (Assert $page) => $page
            ->component('tower')
            ->has('floors', 2)
            ->where('cleared', 1)
            ->where('stats.minAttack', 20));
    }

    public function test_winning_a_new_floor_records_the_battle_and_pays_out()
    {
        $character = Character::factory()->create(['gold' => 0]);
        $this->weakFloor(1, ['exp' => 130]);
        $this->actingAs($character->user);

        $response = $this->post(route('tower.fight', 1));

        $battle = Battle::sole();
        $response->assertRedirect(route('battles.show', $battle));
        $this->assertTrue($battle->won);
        $this->assertSame(1, $battle->floor);
        $this->assertSame('end', last($battle->log['events'])['type']);
        // The battle HUD shows both sides' level and chakra.
        $this->assertSame(1, $battle->log['fighters'][0]['level']);
        $this->assertSame(56, $battle->log['fighters'][0]['maxMp']);
        $this->assertSame(110, $battle->log['fighters'][1]['maxMp']);

        $character->refresh();
        $this->assertSame(1, $character->tower_floor);
        $this->assertSame(2, $character->level); // 130 exp > 120 needed for level 2
        $this->assertSame(10, $character->exp);
        $this->assertSame(config('game.tower.gold_base') + config('game.tower.gold_per_floor'), $character->gold);
        $this->assertSame(config('game.tower.coupons_per_first_clear'), $character->coupons);
        $honor = config('game.honor.tower_first_clear');
        $this->assertSame($honor, $character->honor);
        $this->assertSame($honor, $character->medals);
        $this->assertEquals(['exp' => 130, 'gold' => 25, 'coupons' => 1, 'honor' => $honor, 'levelUp' => true, 'firstClear' => true, 'drop' => null], $battle->rewards);
    }

    public function test_a_first_clear_drops_the_best_gear_the_opponent_level_allows()
    {
        $character = Character::factory()->create();
        Equipment::factory()->slot('hat', 3, ['defense' => 24])->create();
        $best = Equipment::factory()->slot('hat', 13, ['defense' => 34])->create();
        Equipment::factory()->slot('hat', 23, ['defense' => 44])->create();
        $this->weakFloor(1, ['level' => 20]);
        $this->actingAs($character->user);

        $this->post(route('tower.fight', 1));

        $this->assertSame($best->code, Battle::sole()->rewards['drop']['code']);
        $piece = $character->gear()->sole();
        $this->assertSame($best->id, $piece->equipment_id);
        $this->assertNull($piece->equipped_slot);
    }

    public function test_replays_drop_gear_only_when_lucky()
    {
        $character = Character::factory()->create(['tower_floor' => 1]);
        Equipment::factory()->create();
        $this->weakFloor(1);
        $this->actingAs($character->user);

        Lottery::alwaysLose(fn () => $this->post(route('tower.fight', 1)));
        $this->assertSame(0, $character->gear()->count());

        Lottery::alwaysWin(fn () => $this->post(route('tower.fight', 1)));
        $this->assertSame(1, $character->gear()->count());
    }

    public function test_replaying_a_cleared_floor_pays_reduced_exp_and_no_gold()
    {
        $character = Character::factory()->create(['tower_floor' => 1, 'gold' => 0]);
        $this->weakFloor(1, ['exp' => 100]);
        $this->actingAs($character->user);

        $this->post(route('tower.fight', 1));

        $character->refresh();
        $this->assertSame(25, $character->exp);
        $this->assertSame(0, $character->gold);
        $this->assertSame(1, $character->tower_floor);
    }

    public function test_floors_beyond_the_next_one_are_locked()
    {
        $character = Character::factory()->create(['tower_floor' => 0]);
        $this->weakFloor(1);
        $this->weakFloor(2);
        $this->actingAs($character->user);

        $this->post(route('tower.fight', 2))->assertForbidden();
        $this->assertDatabaseCount('battles', 0);
    }

    public function test_fights_per_minute_are_limited()
    {
        config(['game.actions_per_minute' => 2]);
        $character = Character::factory()->create();
        $this->weakFloor(1);
        $this->actingAs($character->user);

        $this->post(route('tower.fight', 1))->assertRedirect();
        $this->post(route('tower.fight', 1))->assertRedirect();
        $this->post(route('tower.fight', 1))->assertTooManyRequests();
        $this->assertDatabaseCount('battles', 2);
    }

    public function test_losing_gives_no_progress_or_rewards()
    {
        $character = Character::factory()->create();
        $this->deadlyFloor(1);
        $this->actingAs($character->user);

        $this->post(route('tower.fight', 1));

        $character->refresh();
        $this->assertFalse(Battle::sole()->won);
        $this->assertSame(0, $character->tower_floor);
        $this->assertSame(0, $character->exp);
    }

    public function test_health_and_chakra_are_fully_restored_after_a_battle()
    {
        $character = Character::factory()->create(['hp' => 5, 'mp' => 5, 'vitals_at' => now()]);
        $this->deadlyFloor(1);
        $this->actingAs($character->user);

        $this->post(route('tower.fight', 1));

        $character->refresh();
        $this->assertSame($character->stats()->maxHp, $character->currentHp());
        $this->assertSame($character->stats()->maxMp, $character->currentMp());
    }

    public function test_health_recovers_with_rest()
    {
        Carbon::setTestNow('2026-10-02 12:00:00');
        $character = Character::factory()->create(['hp' => 30, 'vitals_at' => now()]);
        $this->actingAs($character->user);

        $this->get(route('tower.show'))->assertInertia(fn (Assert $page) => $page->where('character.hp', 30));

        Carbon::setTestNow(now()->addMinutes(2)); // +5.5 per minute of 110 max
        $this->get(route('tower.show'))->assertInertia(fn (Assert $page) => $page->where('character.hp', 41));
    }

    public function test_players_can_only_watch_their_own_battles()
    {
        $character = Character::factory()->create();
        $this->weakFloor(1);
        $this->actingAs($character->user);
        $this->post(route('tower.fight', 1));
        $battle = Battle::sole();

        $this->get(route('battles.show', $battle))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('battle')
            ->where('battle.won', true)
            ->has('battle.log.fighters', 2));

        $this->actingAs(Character::factory()->create()->user);
        $this->get(route('battles.show', $battle))->assertNotFound();
    }
}

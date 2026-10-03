<?php

namespace Tests\Feature;

use App\Models\Battle;
use App\Models\Character;
use App\Models\Dungeon;
use App\Models\DungeonRun;
use App\Models\User;
use Database\Factories\DungeonFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DungeonTest extends TestCase
{
    use RefreshDatabase;

    private function ninja(array $overrides = []): Character
    {
        return Character::factory()->create(['level' => 20, 'gold' => 0, 'coupons' => 0, ...$overrides]);
    }

    /**
     * @param  list<int>  $wavesPerStage
     */
    private function dungeon(array $wavesPerStage = [2], array $monster = [], array $overrides = []): Dungeon
    {
        return Dungeon::factory()->create([
            'stages' => array_map(fn (int $waves) => DungeonFactory::stage($waves, $monster), $wavesPerStage),
            ...$overrides,
        ]);
    }

    public function test_players_need_a_character_first()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dungeons.index'))->assertRedirect(route('character.create'));
    }

    public function test_dungeon_list_shows_runs_left_today()
    {
        $character = $this->ninja();
        $dungeon = $this->dungeon(overrides: ['daily_runs' => 3]);
        DungeonRun::factory()->for($character)->for($dungeon)->create(['status' => 'failed']);
        $this->actingAs($character->user);

        $this->get(route('dungeons.index'))->assertInertia(fn (Assert $page) => $page
            ->component('dungeons/index')
            ->has('dungeons', 1)
            ->where('dungeons.0.runs_left', 2)
            ->where('dungeons.0.stages', 1));
    }

    public function test_entering_needs_the_minimum_level()
    {
        $character = $this->ninja(['level' => 10]);
        $dungeon = $this->dungeon(overrides: ['min_level' => 16]);
        $this->actingAs($character->user);

        $this->post(route('dungeons.enter', $dungeon))->assertSessionHasErrors('dungeon');

        $this->assertSame(0, DungeonRun::count());
    }

    public function test_daily_runs_are_limited()
    {
        $character = $this->ninja();
        $dungeon = $this->dungeon(overrides: ['daily_runs' => 1]);
        DungeonRun::factory()->for($character)->for($dungeon)->create(['status' => 'cleared']);
        $this->actingAs($character->user);

        $this->post(route('dungeons.enter', $dungeon))->assertSessionHasErrors('dungeon');
    }

    public function test_only_one_dungeon_at_a_time()
    {
        $character = $this->ninja();
        $other = $this->dungeon();
        DungeonRun::factory()->for($character)->for($other)->create();
        $this->actingAs($character->user);

        $this->post(route('dungeons.enter', $this->dungeon()))->assertSessionHasErrors('dungeon');
    }

    public function test_winning_a_wave_moves_on_and_keeps_the_wounds()
    {
        $character = $this->ninja();
        $dungeon = $this->dungeon([2], ['max_atk' => 3, 'min_atk' => 3, 'max_hp' => 30]);
        $this->actingAs($character->user);

        $this->post(route('dungeons.enter', $dungeon))->assertRedirect(route('dungeons.show', $dungeon));
        $run = DungeonRun::sole();
        $response = $this->post(route('dungeon-runs.fight', $run));

        $battle = Battle::sole();
        $response->assertRedirect(route('battles.show', $battle));
        $this->assertTrue($battle->won);
        $this->assertSame($run->id, $battle->dungeon_run_id);
        $this->assertSame([0, 1, 'active'], [$run->refresh()->stage, $run->wave, $run->status]);
        $this->assertSame(10 * config('game.dungeons.exp_multiplier'), $battle->rewards['exp']);
        $this->assertNotNull($character->refresh()->hp, 'wounds carry over between waves');
    }

    public function test_clearing_the_last_boss_pays_stage_and_dungeon_rewards()
    {
        $character = $this->ninja();
        $dungeon = $this->dungeon([1], overrides: ['reward_exp' => 0, 'reward_gold' => 500]);
        $run = DungeonRun::factory()->for($character)->for($dungeon)->create();
        $this->actingAs($character->user);

        $this->post(route('dungeon-runs.fight', $run));

        $this->assertSame('cleared', $run->refresh()->status);
        $rewards = Battle::sole()->rewards;
        $this->assertSame(100 + 500, $rewards['gold']); // stage + dungeon
        $this->assertTrue($rewards['dungeonCleared']);
        $this->assertSame(100 + 500, $character->refresh()->gold);
        $this->assertSame(config('game.dungeons.clear_coupons'), $character->coupons);
        $this->assertSame(config('game.honor.dungeon_clear'), $rewards['honor']);
        $this->assertSame(config('game.honor.dungeon_clear'), $character->honor);
        $this->assertSame(config('game.honor.dungeon_clear'), $character->medals);
    }

    public function test_clearing_a_stage_restores_the_ninja_and_bosses_fight_solo()
    {
        $character = $this->ninja();
        $dungeon = $this->dungeon([1, 1], ['max_hp' => 30, 'min_atk' => 10, 'max_atk' => 10]);
        $run = DungeonRun::factory()->for($character)->for($dungeon)->create();
        $this->actingAs($character->user);

        $this->post(route('dungeon-runs.fight', $run));

        $this->assertSame('Camp Outpost', Battle::sole()->rewards['stageCleared']);
        $this->assertNull($character->refresh()->hp, 'a stage clear restores health');
        $this->assertSame(intdiv(30 * config('game.dungeons.solo_boss_percent'), 100), $dungeon->monster(0, 0)->max_hp);
    }

    public function test_losing_a_wave_ends_the_run()
    {
        $character = $this->ninja();
        $dungeon = $this->dungeon([2], ['max_hp' => 100000, 'min_atk' => 100000, 'max_atk' => 100000, 'priority' => 100]);
        $run = DungeonRun::factory()->for($character)->for($dungeon)->create();
        $this->actingAs($character->user);

        $this->post(route('dungeon-runs.fight', $run));

        $this->assertFalse(Battle::sole()->won);
        $this->assertSame('failed', $run->refresh()->status);
    }

    public function test_finished_runs_and_other_players_runs_cannot_be_fought()
    {
        $character = $this->ninja();
        $dungeon = $this->dungeon();
        $finished = DungeonRun::factory()->for($character)->for($dungeon)->create(['status' => 'cleared']);
        $theirs = DungeonRun::factory()->for(Character::factory())->for($dungeon)->create();
        $this->actingAs($character->user);

        $this->post(route('dungeon-runs.fight', $finished))->assertSessionHasErrors('dungeon');
        $this->post(route('dungeon-runs.fight', $theirs))->assertNotFound();
    }

    public function test_leaving_ends_the_run()
    {
        $character = $this->ninja();
        $run = DungeonRun::factory()->for($character)->for($this->dungeon())->create();
        $this->actingAs($character->user);

        $this->post(route('dungeon-runs.leave', $run))->assertRedirect(route('dungeons.index'));

        $this->assertSame('left', $run->refresh()->status);
    }

    public function test_dungeon_page_shows_the_stages_and_the_active_run()
    {
        $character = $this->ninja();
        $dungeon = $this->dungeon([2, 1]);
        $run = DungeonRun::factory()->for($character)->for($dungeon)->create(['stage' => 1, 'wave' => 0]);
        $this->actingAs($character->user);

        $this->get(route('dungeons.show', $dungeon))->assertInertia(fn (Assert $page) => $page
            ->component('dungeons/show')
            ->has('dungeon.stages', 2)
            ->has('dungeon.stages.0.waves', 2)
            ->where('run.id', $run->id)
            ->where('run.stage', 1));
    }
}

<?php

namespace App\Actions;

use App\Game\BattleLog;
use App\Game\BattleSimulator;
use App\Game\Leveling;
use App\Models\Battle;
use App\Models\Character;
use App\Models\DungeonRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FightDungeonWave
{
    public function __construct(
        private readonly BattleSimulator $simulator,
        private readonly DropGear $dropGear,
    ) {}

    /**
     * Fight the run's next wave leader. Wounds carry over between waves; a loss
     * ends the run, the last wave of a stage pays the stage reward and the last
     * stage pays the dungeon's.
     *
     * @throws ValidationException when the run is over or the ninja cannot fight
     */
    public function handle(Character $character, DungeonRun $run): Battle
    {
        return DB::transaction(function () use ($character, $run) {
            $ninja = Character::query()->lockForUpdate()->findOrFail($character->id);
            $run = DungeonRun::query()->with('dungeon')->lockForUpdate()->findOrFail($run->id);
            $problem = match (true) {
                $run->status !== 'active' => 'This run is over. Enter the dungeon again.',
                $ninja->currentHp() < 1 => 'You are too hurt to fight. Rest or drink a potion first.',
                default => null,
            };

            if ($problem !== null) {
                throw ValidationException::withMessages(['dungeon' => $problem]);
            }

            $monster = $run->dungeon->monster($run->stage, $run->wave);
            $player = $ninja->combatant();
            $before = ['level' => $ninja->level, 'mp' => $ninja->currentMp(), 'maxMp' => $ninja->stats()->maxMp];
            $opponent = $monster->combatant();
            $result = $this->simulator->simulate($player, $opponent);
            $won = $result['winner'] === 0;
            $rewards = $won ? $this->advance($run, $monster->exp) : ['exp' => 0, 'gold' => 0, 'coupons' => 0, 'honor' => 0, 'stageCleared' => null, 'dungeonCleared' => false];
            $drop = $won && $monster->is_boss ? $this->dropGear->handle($ninja, $monster->level) : null;
            $run->forceFill(['status' => $won ? $run->status : 'failed'])->save();

            [$level, $exp] = Leveling::gain($ninja->level, $ninja->exp, $rewards['exp']);
            $ninja->forceFill([
                'level' => $level,
                'exp' => $exp,
                'gold' => $ninja->gold + $rewards['gold'],
                'coupons' => $ninja->coupons + $rewards['coupons'],
                'honor' => $ninja->honor + $rewards['honor'],
                'medals' => $ninja->medals + $rewards['honor'],
            ]);
            $ninja->setVitals($result['hp'][0], $result['mp'][0]);
            if ($rewards['stageCleared'] !== null) {
                // Stages are checkpoints: the ninja starts the next one fully recovered.
                $ninja->forceFill(['hp' => null, 'mp' => null, 'vitals_at' => null]);
            }
            $ninja->save();

            return $ninja->battles()->create([
                'dungeon_run_id' => $run->id,
                'won' => $won,
                'log' => [
                    'fighters' => BattleLog::fighters($ninja, $player, $before, $monster, $opponent),
                    'events' => $result['events'],
                ],
                'rewards' => [...$rewards, 'levelUp' => $level > $before['level'], 'firstClear' => false, 'drop' => $drop?->summary()],
            ]);
        });
    }

    /**
     * Move the run past a won wave and add up what it pays.
     *
     * @return array{exp: int, gold: int, coupons: int, honor: int, stageCleared: string|null, dungeonCleared: bool}
     */
    private function advance(DungeonRun $run, int $waveExp): array
    {
        $dungeon = $run->dungeon;
        $stage = $dungeon->stages[$run->stage];
        $rewards = ['exp' => $waveExp * config('game.dungeons.exp_multiplier'), 'gold' => 0, 'coupons' => 0, 'honor' => 0, 'stageCleared' => null, 'dungeonCleared' => false];

        if ($run->wave + 1 < count($stage['waves'])) {
            $run->wave++;

            return $rewards;
        }

        $rewards = [...$rewards, 'exp' => $rewards['exp'] + $stage['reward_exp'], 'gold' => $stage['reward_gold'], 'stageCleared' => $stage['name']];
        [$run->stage, $run->wave] = [$run->stage + 1, 0];

        if ($run->stage < count($dungeon->stages)) {
            return $rewards;
        }

        $run->status = 'cleared';

        return [
            ...$rewards,
            'exp' => $rewards['exp'] + $dungeon->reward_exp,
            'gold' => $rewards['gold'] + $dungeon->reward_gold,
            'coupons' => config('game.dungeons.clear_coupons'),
            'honor' => config('game.honor.dungeon_clear'),
            'dungeonCleared' => true,
        ];
    }
}

<?php

namespace App\Actions;

use App\Game\BattleSimulator;
use App\Game\Leveling;
use App\Models\Battle;
use App\Models\Character;
use App\Models\TowerFloor;
use Illuminate\Support\Facades\DB;

class ChallengeTowerFloor
{
    public function __construct(private readonly BattleSimulator $simulator) {}

    /**
     * Fight the floor's opponent, apply the outcome and keep the log for replay.
     *
     * The character row is locked so a double-submitted fight cannot pay twice.
     */
    public function handle(Character $character, TowerFloor $floor): Battle
    {
        return DB::transaction(function () use ($character, $floor) {
            $ninja = Character::query()->lockForUpdate()->findOrFail($character->id);
            $player = $ninja->combatant();
            $opponent = $floor->combatant();
            $result = $this->simulator->simulate($player, $opponent);
            $won = $result['winner'] === 0;
            $firstClear = $won && $floor->floor > $ninja->tower_floor;
            $rewards = $this->rewards($ninja, $floor, $won, $firstClear);

            [$level, $exp] = Leveling::gain($ninja->level, $ninja->exp, $rewards['exp']);
            $ninja->forceFill([
                'level' => $level,
                'exp' => $exp,
                'gold' => $ninja->gold + $rewards['gold'],
                'tower_floor' => $firstClear ? $floor->floor : $ninja->tower_floor,
            ]);
            // Win or lose, the ninja walks out of the tower fully recovered.
            $ninja->forceFill(['hp' => null, 'mp' => null, 'vitals_at' => null]);
            $ninja->save();

            return $ninja->battles()->create([
                'floor' => $floor->floor,
                'won' => $won,
                'log' => [
                    'fighters' => [
                        [...$player->toArray(), 'avatar' => $ninja->avatar],
                        [...$opponent->toArray(), 'level' => $floor->level, 'isBoss' => $floor->is_boss, 'art' => $floor->art],
                    ],
                    'events' => $result['events'],
                ],
                'rewards' => [...$rewards, 'levelUp' => $level > $character->level],
            ]);
        });
    }

    /**
     * @return array{exp: int, gold: int, firstClear: bool}
     */
    private function rewards(Character $ninja, TowerFloor $floor, bool $won, bool $firstClear): array
    {
        $tower = config('game.tower');

        if (! $won) {
            return ['exp' => 0, 'gold' => 0, 'firstClear' => false];
        }

        if ($firstClear) {
            return [
                'exp' => $floor->exp,
                'gold' => $tower['gold_base'] + $floor->floor * $tower['gold_per_floor'],
                'firstClear' => true,
            ];
        }

        return ['exp' => intdiv($floor->exp * $tower['replay_exp_percent'], 100), 'gold' => 0, 'firstClear' => false];
    }
}

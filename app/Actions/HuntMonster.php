<?php

namespace App\Actions;

use App\Game\BattleLog;
use App\Game\BattleSimulator;
use App\Game\Leveling;
use App\Models\Battle;
use App\Models\Character;
use App\Models\FieldMonster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Lottery;
use Illuminate\Validation\ValidationException;

class HuntMonster
{
    public function __construct(
        private readonly BattleSimulator $simulator,
        private readonly DropGear $dropGear,
    ) {}

    /**
     * Fight a monster on a hunting ground. Unlike the tower, the ninja keeps
     * the wounds: health and chakra carry over until they recover or drink a potion.
     *
     * @throws ValidationException when the ninja is too weak or too low level to go
     */
    public function handle(Character $character, FieldMonster $monster): Battle
    {
        return DB::transaction(function () use ($character, $monster) {
            $ninja = Character::query()->lockForUpdate()->findOrFail($character->id);
            $field = $monster->field;
            $problem = match (true) {
                $ninja->level < $field->level => "{$field->name} needs level {$field->level}.",
                $ninja->currentHp() < 1 => 'You are too hurt to fight. Rest or drink a potion first.',
                default => null,
            };

            if ($problem !== null) {
                throw ValidationException::withMessages(['monster' => $problem]);
            }

            $config = config('game.fields');
            $player = $ninja->combatant();
            $before = ['level' => $ninja->level, 'mp' => $ninja->currentMp(), 'maxMp' => $ninja->stats()->maxMp];
            $opponent = $monster->combatant();
            $result = $this->simulator->simulate($player, $opponent);
            $won = $result['winner'] === 0;
            $exp = $won ? $monster->exp * $config['exp_multiplier'] : 0;
            $gold = $won ? $monster->level * $config['gold_per_level'] : 0;
            $dropPercent = $monster->is_boss ? $config['boss_drop_percent'] : $config['drop_percent'];
            $drop = $won && Lottery::odds($dropPercent, 100)->choose() ? $this->dropGear->handle($ninja, $monster->level) : null;

            [$level, $levelExp] = Leveling::gain($ninja->level, $ninja->exp, $exp);
            $ninja->forceFill([
                'level' => $level,
                'exp' => $levelExp,
                'gold' => $ninja->gold + $gold,
                'bosses_defeated' => $ninja->bosses_defeated + ($won && $monster->is_boss ? 1 : 0),
            ]);
            $ninja->setVitals($result['hp'][0], $result['mp'][0]);
            $ninja->save();

            return $ninja->battles()->create([
                'field_monster_id' => $monster->id,
                'won' => $won,
                'log' => [
                    'fighters' => BattleLog::fighters($ninja, $player, $before, $monster, $opponent),
                    'events' => $result['events'],
                ],
                'rewards' => [
                    'exp' => $exp,
                    'gold' => $gold,
                    'levelUp' => $level > $before['level'],
                    'firstClear' => false,
                    'drop' => $drop?->summary(),
                ],
            ]);
        });
    }
}

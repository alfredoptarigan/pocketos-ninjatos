<?php

namespace App\Game;

use App\Models\Character;
use App\Models\FieldMonster;
use App\Models\TowerFloor;

/**
 * The fighters part of a stored battle log, as the replay and battle HUD read it.
 */
final class BattleLog
{
    /**
     * @param  array{level: int, mp: int, maxMp: int}  $before  the ninja as the battle started
     * @return array<int, array<string, mixed>>
     */
    public static function fighters(Character $ninja, Combatant $player, array $before, TowerFloor|FieldMonster $foe, Combatant $opponent): array
    {
        return [
            [...$player->toArray(), 'avatar' => $ninja->look(), ...$before],
            [
                ...$opponent->toArray(),
                'level' => $foe->level,
                'mp' => $foe->max_mp,
                'maxMp' => $foe->max_mp,
                'isBoss' => $foe->is_boss,
                // Hunting-ground fights take place on the area's own backdrop.
                'art' => $foe instanceof FieldMonster ? [...$foe->art, 'background' => $foe->field->background] : $foe->art,
            ],
        ];
    }
}

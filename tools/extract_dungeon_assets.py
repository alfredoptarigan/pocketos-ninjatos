#!/usr/bin/env python3
"""Extract the dungeons (original single-player "tollgates") from the Pockie Ninja backup.

Usage: python3 tools/extract_dungeon_assets.py <path-to-game-pockieninja> [--hd]
Run tools/extract_outfit_assets.py first: bosses that are Naruto/Bleach
characters reuse their outfit art.

tollgate (a dungeon per difficulty) -> subtollgate (its stages) ->
fightmonsterpoint / monstergroup (five waves per stage, three npcs each) ->
normalnpc (stats). The original waves were team fights against three monsters;
our battles are one on one, so each wave sends its leader: the third npc, which
carries the wave's exp and is the stage boss in the last wave.

Writes database/data/dungeons.json (DungeonSeeder), stage pictures to
public/game-assets/dungeons/<code>.jpg and stand-in monster art (the backup has
none for dungeon npcs) to public/game-assets/monsters/. Output is gitignored.
"""

from __future__ import annotations

import json
import re
import shutil
import sys
from pathlib import Path

from amf3 import load_compressed
from extract_item_assets import latest_table
from extract_tower_assets import STATS, extract_art, number
from extract_world_assets import StandIns
from swf import first_jpeg, read_swf
from upscale import available as upscaler_available

SOURCE = 'apache/source'
ROOT = Path(__file__).resolve().parent.parent
ASSETS = ROOT / 'public' / 'game-assets'
DATA_OUT = ROOT / 'database' / 'data' / 'dungeons.json'
HD_SCALE = 2

# Original Hard column -> difficulty: 1 "strong" monsters (less gold), 2 normal ones,
# per lg_Common_TollGateHard/Normal. 3 ("Super") needs an Abyss Pass item we do not have.
DIFFICULTIES = {'': 'trial', '1': 'hard', '2': 'normal'}
# Dungeon npcs rate dodge, parry and crit about ten times higher than tower and field
# npcs (72 vs 2-28); our engine reads them as percent chances, so scale them down.
RATING_DIVISOR = 10
RATINGS = ('dodge', 'parry', 'crit')
LEADER_COLUMNS = ('MonsterConfig3', 'MonsterConfig2', 'MonsterConfig1')
# Later waves have no NpcExp in the data; ours follows the early waves (about 3 per level).
EXP_PER_LEVEL = 3
BATTLE_BACKGROUND = 'fightbg_3102'


def table_rows(datatable: Path, name: str) -> list[dict]:
    """All rows, header row included when the table has one (lookups by id skip it)."""
    table = load_compressed(latest_table(datatable, name))
    columns = list(table)
    return [{column: table[column][i] for column in columns} for i in range(len(table[columns[0]]))]


def last_number(value: str) -> int:
    """RewardMoney is sometimes "0,1100"; the last figure is the gold."""
    return number(str(value or 0).split(',')[-1])


def leader(group: dict) -> str:
    return next(group[column] for column in LEADER_COLUMNS if group[column])


def dungeons(tables: dict[str, list[dict]], english: dict) -> list[dict]:
    """Dungeons with stages and waves; each wave holds its leader's code and stats (art comes later)."""
    npcs = {row['ID']: row for row in tables['normalnpc']}
    groups = {row['MonsterID']: row for row in tables['monstergroup']}
    found = []
    for gate in tables['tollgate']:
        if gate['Hard'] not in DIFFICULTIES:
            continue
        stages = sorted((s for s in tables['subtollgate'] if s['ParentTollGateID'] == gate['ConfigID']), key=lambda s: number(s['Seq']))
        found.append({
            'code': gate['ConfigID'],
            'name': english['lg_' + gate['ConfigName']],
            'difficulty': DIFFICULTIES[gate['Hard']],
            'min_level': number(gate['RequirLevel']),
            'max_level': number(gate['MaxLevel']),
            'daily_runs': number(gate['TotalTimes']),
            'reward_exp': number(gate['RewardExp']),
            'reward_gold': last_number(gate['RewardMoney']),
            'picture_clip': gate['TollGateMap'],
            'stages': [stage(s, tables['fightmonsterpoint'], groups, npcs, english) for s in stages],
        })
    return found


def stage(row: dict, points: list[dict], groups: dict, npcs: dict, english: dict) -> dict:
    waves = sorted((p for p in points if p['ParentSubTollGateID'] == row['ID']), key=lambda p: number(p['Seq']))
    return {
        'name': english['lg_' + row['BeachheadName']],
        'recommended': row['Recommendlevel'],
        'reward_exp': number(row['RewardExp']),
        'reward_gold': last_number(row['RewardMoney']),
        'waves': [
            wave_leader(npcs[leader(groups[point['ID']])], english, boss=index == len(waves) - 1)
            for index, point in enumerate(waves)
        ],
    }


def wave_leader(npc: dict, english: dict, boss: bool) -> dict:
    level = number(npc['Level'])
    stats = {field: number(npc[column]) for field, column in STATS.items()}
    return {
        'code': npc['ID'],
        'name': english['lg_' + npc['Name']],
        'name_key': npc['Name'],
        'is_boss': boss,
        **stats,
        **{field: stats[field] // RATING_DIVISOR for field in RATINGS},
        'exp': number(npc['NpcExp']) or level * EXP_PER_LEVEL,
    }


def outfit_art(name_key: str) -> dict | None:
    """Bosses named after an avatar ("name_avatar45") fight in that outfit's art."""
    match = re.fullmatch(r'name_avatar(\d+)', name_key)
    if not match:
        return None
    for sex in ('0', '1'):
        folder = f'{sex}_{match.group(1)}'
        if (ASSETS / 'characters' / folder / 'motions.json').is_file():
            url = f'/game-assets/characters/{folder}'
            return {'type': 'motion', 'motions': f'{url}/motions.json', 'face': f'{url}/face.png'}
    return None


def copy_picture(source: Path, clip: str, code: str) -> str:
    pictures = sorted((source / 'movieclip/ui').glob(f'{clip.lower()}*.jpg'))
    out = ASSETS / 'dungeons'
    out.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(pictures[-1], out / f'{code}.jpg')
    return f'/game-assets/dungeons/{code}.jpg'


def main() -> None:
    if len(sys.argv) not in (2, 3) or sys.argv[2:] not in ([], ['--hd']):
        sys.exit(__doc__)
    scale = HD_SCALE if '--hd' in sys.argv else 1
    if scale > 1 and not upscaler_available():
        sys.exit('Real-ESRGAN not found; see tools/upscale.py.')
    source = Path(sys.argv[1]).expanduser() / SOURCE
    binary = source / 'binary'
    languages = sorted((binary / 'keyvaluetable').glob('language.s*.kv'), key=lambda p: int(p.name.split('.s')[1].split('.')[0]))
    english = load_compressed(languages[-1])
    chinese = load_compressed(binary / 'lg/language.lg')
    names = ('tollgate', 'subtollgate', 'fightmonsterpoint', 'monstergroup', 'normalnpc')
    tables = {name: table_rows(binary / 'datatable', name) for name in names}

    background = ASSETS / 'battle' / 'backgrounds' / f'{BATTLE_BACKGROUND}.jpg'
    background.parent.mkdir(parents=True, exist_ok=True)
    clip = next((source / 'movieclip/ui/fightbg').glob(f'{BATTLE_BACKGROUND}.*swf'))
    background.write_bytes(first_jpeg(read_swf(clip)))

    stand_ins = StandIns(source / 'movieclip/motion/mob')
    art_by_name: dict[str, dict] = {}
    found = dungeons(tables, english)
    for dungeon in found:
        dungeon['picture'] = copy_picture(source, dungeon.pop('picture_clip'), dungeon['code'])
        for wave in (wave for stage in dungeon['stages'] for wave in stage['waves']):
            name_key = wave.pop('name_key')
            if name_key not in art_by_name:
                stand_in = stand_ins.take(chinese.get('lg_' + name_key, ''), wave['is_boss'])
                art_by_name[name_key] = outfit_art(name_key) or extract_art(source, f'MapUserFace_{stand_in.upper()}', scale)
            wave['art'] = {**art_by_name[name_key], 'background': f'/game-assets/battle/backgrounds/{BATTLE_BACKGROUND}.jpg'}

    DATA_OUT.parent.mkdir(parents=True, exist_ok=True)
    DATA_OUT.write_text(json.dumps(found, indent=1, ensure_ascii=False))
    waves = sum(len(stage['waves']) for dungeon in found for stage in dungeon['stages'])
    print(f'{len(found)} dungeons, {waves} waves -> {DATA_OUT.relative_to(ROOT)}')


if __name__ == '__main__':
    main()

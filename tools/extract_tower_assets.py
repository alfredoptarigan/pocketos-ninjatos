#!/usr/bin/env python3
"""Extract the Training Tower (original "single gate") from the Pockie Ninja backup.

Usage: python3 tools/extract_tower_assets.py <path-to-game-pockieninja>
Requires Pillow.

Writes:
  database/data/tower.json                  170 floors: opponent stats, exp, art (TowerSeeder)
  public/game-assets/monsters/<id>/         motions.png/json + face.png, or portrait.png for bosses
  public/game-assets/battle/background.jpg  battle backdrop
  public/game-assets/music/singlegate.mp3   tower music
Output is gitignored: it is derived from copyrighted game files.
"""

from __future__ import annotations

import json
import re
import shutil
import sys
from pathlib import Path

from amf3 import load_compressed
from motion import find_motions, write_motion_sheet
from swf import first_jpeg, read_swf

ROOT = Path(__file__).resolve().parent.parent
ASSETS = ROOT / 'public' / 'game-assets'
DATA_OUT = ROOT / 'database' / 'data' / 'tower.json'
SOURCE = 'apache/source'

# Opponent names (the backup only has Chinese); floors 151-170 have none.
NAMES = {
    '路德本': 'Rudbornn', '食人虚': 'Man-eating Hollow', '基利安': 'Gillian',
    '亚罗尼洛': 'Aaroniero', '葛力姆乔': 'Grimmjow', '虚化葛力姆乔': 'Hollowfied Grimmjow',
    '路鲁': 'Luppi', '怨念虚': 'Grudge Hollow', '鬼魅虚': 'Phantom Hollow', '罗伊': 'Di Roy',
    '萨尔阿波罗': 'Szayelaporro', '诺伊特拉': 'Nnoitra', '乌尔奇奥拉': 'Ulquiorra',
    '白乌尔奇奥拉': 'White Ulquiorra', '虚化乌尔奇奥拉': 'Hollowfied Ulquiorra', '牙密': 'Yammy',
    '拜勒岗': 'Barragan', '史塔克': 'Starrk', '赫丽贝尔': 'Harribel', '市丸银': 'Gin Ichimaru',
}
UNNAMED = 'Tower Guardian'

STATS = {
    'level': 'Level', 'max_hp': 'MaxHP', 'max_mp': 'MaxMP', 'min_atk': 'MinAtk', 'max_atk': 'MaxAtk',
    'defense': 'Defense', 'crit': 'CritMul', 'crit_multiplier': 'CritAttach', 'dodge': 'DodgeMul',
    'parry': 'ParryMul', 'counter': 'CounterMul', 'priority': 'PriorityMul',
}
# Animated opponents use map monster art; everything else is a boss with a portrait.
ANIMATED_PREFIX = 'MapUserFace_'


def latest_table(datatable: Path, name: str) -> dict:
    versions = sorted(datatable.glob(f'{name}.s*.tab'), key=lambda p: int(p.name.split('.s')[1].split('.')[0]))
    return load_compressed(versions[-1])


def number(value) -> int:
    return int(value or 0)


def art_id(resource_id: str) -> str:
    """'MapUserFace_N32051' -> 'n32051'."""
    return 'n' + re.sub(r'\D', '', resource_id.split('_')[-1])


def extract_art(source: Path, resource_id: str) -> dict:
    key = art_id(resource_id)
    out = ASSETS / 'monsters' / key
    out.mkdir(parents=True, exist_ok=True)
    url = f'/game-assets/monsters/{key}'

    if resource_id.startswith(ANIMATED_PREFIX):
        folder = next((source / 'movieclip/motion/mob').glob(f'*/{key}'))
        number_id = key[1:]
        if not (out / 'motions.json').exists():
            write_motion_sheet(key, find_motions(folder, f'motion_{number_id}_{{action}}*.swf'), out)
        face = sorted((source / 'bitmap/userfaceavatar/mob').glob(f'userface_{key}.*png'))
        if face:
            shutil.copyfile(face[0], out / 'face.png')
        return {'type': 'motion', 'motions': f'{url}/motions.json', 'face': f'{url}/face.png'}

    portrait = sorted((source / 'bitmap/npcbackphoto').glob(f'{key}.*png'))[0]
    shutil.copyfile(portrait, out / 'portrait.png')
    return {'type': 'portrait', 'portrait': f'{url}/portrait.png', 'face': f'{url}/portrait.png'}


def main() -> None:
    if len(sys.argv) != 2:
        sys.exit(__doc__)
    source = Path(sys.argv[1]).expanduser() / SOURCE
    datatable = source / 'binary/datatable'
    npcs = latest_table(datatable, 'singlegatenpc')
    floor_exp = latest_table(datatable, 'sgategetexp')
    language = load_compressed(source / 'binary/lg/language.lg')
    exp_by_floor = {int(order): number(exp) for order, exp in zip(floor_exp['Order'], floor_exp['Exp'])}

    floors = []
    for index in range(1, len(npcs['ID'])):  # row 0 holds column descriptions
        floor = index
        resource_id = npcs['ResourceID'][index]
        chinese = language.get('lg_' + npcs['Name'][index])
        floors.append({
            'floor': floor,
            'code': npcs['ID'][index],
            'name': NAMES.get(chinese, UNNAMED),
            'is_boss': not resource_id.startswith(ANIMATED_PREFIX),
            **{field: number(npcs[column][index]) for field, column in STATS.items()},
            # The last floor has no exp row; it reuses the previous floor's reward.
            'exp': exp_by_floor.get(floor) or exp_by_floor[max(exp_by_floor)],
            'art': extract_art(source, resource_id),
        })

    DATA_OUT.parent.mkdir(parents=True, exist_ok=True)
    DATA_OUT.write_text(json.dumps(floors, indent=1, ensure_ascii=False))

    battle = ASSETS / 'battle'
    battle.mkdir(parents=True, exist_ok=True)
    background = source / 'movieclip/scene/battle/scene_background_battle.swf'
    (battle / 'background.jpg').write_bytes(first_jpeg(read_swf(background)))

    music = ASSETS / 'music'
    music.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(sorted((source / 'music').glob('singlegate*.mp3'))[0], music / 'singlegate.mp3')

    bosses = sum(f['is_boss'] for f in floors)
    print(f'Wrote {len(floors)} tower floors ({bosses} bosses) to {DATA_OUT.relative_to(ROOT)}')


if __name__ == '__main__':
    main()

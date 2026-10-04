#!/usr/bin/env python3
"""Extract the Training Tower (original "single gate") from the Pockie Ninja backup.

Usage: python3 tools/extract_tower_assets.py <path-to-game-pockieninja> [--hd]
--hd upscales opponent art 2x with Real-ESRGAN (see tools/upscale.py).
Requires Pillow.

Writes:
  database/data/tower.json                  170 floors: opponent stats, exp, art (TowerSeeder)
  public/game-assets/monsters/<id>/         motions.png/json + face.png, or portrait.png for bosses
                                            without battle art
  public/game-assets/characters/<sex>_<id>/ motions of bosses that are avatars (AvatarUserFace_N9..)
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
from extract_outfit_assets import extract_art as extract_avatar_art
from motion import find_motions, write_motion_sheet
from upscale import available as upscaler_available
from upscale import upscale_file
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
# Animated opponents use map monster art; everything else is a boss. Most
# bosses are avatars in costume ("AvatarUserFace_N9<sex><avatar id>", the
# avatar id being the costume level * 100 + the outfit id) and fight in that
# avatar's motions; the rest (Akatsuki "N9001xx", "TGateUserFace_") only
# have a portrait.
ANIMATED_PREFIX = 'MapUserFace_'
AVATAR_RESOURCE = re.compile(r'AvatarUserFace_N9[01](\d{3})')
BUILT: set[str] = set()
ULTIMATE_DIR = 'movieclip/fighteffect/bigeffect'
ULTIMATE_BASE = 1900
BACKGROUNDS_DIR = 'movieclip/ui/fightbg'
# arena.jpg carries red guide lines; 103001 and 4001 belong to special events.
SKIPPED_BACKGROUNDS = {'arena', 'fightbg_103001', 'fightbg_4001'}
FLOORS_PER_BACKGROUND = 10
# Floors 151-170 (added later) rate dodge, block and crit ten times higher,
# like the dungeon npcs; they are divided back to percent.
LATE_FLOORS_FROM = 151
RATING_DIVISOR = 10
RATINGS = ('dodge', 'parry', 'crit')
HD_SCALE = 2


def latest_table(datatable: Path, name: str) -> dict:
    versions = sorted(datatable.glob(f'{name}.s*.tab'), key=lambda p: int(p.name.split('.s')[1].split('.')[0]))
    return load_compressed(versions[-1])


def number(value) -> int:
    return int(value or 0)


def stats(npcs: dict, index: int) -> dict[str, int]:
    """The floor's battle stats, with late-floor ratings brought to the percent scale."""
    found = {field: number(npcs[column][index]) for field, column in STATS.items()}
    if index >= LATE_FLOORS_FROM:
        found.update({field: found[field] // RATING_DIVISOR for field in RATINGS})
    return found


def art_id(resource_id: str) -> str:
    """'MapUserFace_N32051' -> 'n32051'."""
    return 'n' + re.sub(r'\D', '', resource_id.split('_')[-1])


def boss_avatar(resource_id: str) -> int | None:
    """'AvatarUserFace_N90277' (Aaroniero +2) -> outfit id 77; None for other art."""
    match = AVATAR_RESOURCE.fullmatch(resource_id)
    return int(match[1]) % 100 if match else None


def file_version(path: Path) -> int:
    """'motion_32056_52.s27942.swf' -> 27942; unversioned files are the oldest."""
    match = re.search(r'\.s(\d+)\.', path.name)
    return int(match[1]) if match else 0


def mob_folder(mob_dir: Path, key: str) -> Path:
    """The motions folder of a map monster.

    A later patch reused an id for a new monster in another kind folder
    (n32056 is Di Roy in human/ and a beast in inhumanboss/); the original,
    which matches the face, is the folder whose newest file is oldest.
    """
    return min(mob_dir.glob(f'*/{key}'), key=lambda folder: max(map(file_version, folder.iterdir()), default=0))


def costume_level(resource_id: str) -> int:
    """'AvatarUserFace_N90277' (Aaroniero +2) -> 2: the boss's outfit upgrade, for its Ultimate."""
    return int(AVATAR_RESOURCE.fullmatch(resource_id)[1]) // 100


def avatar_art(source: Path, avatar: int, scale: int) -> dict | None:
    """Motions and face of the avatar a boss is, if the backup has them (either sex)."""
    for sex in ('0', '1'):
        key = f'{sex}_{avatar}'
        if key in BUILT or extract_avatar_art(source, key, scale):
            BUILT.add(key)
            url = f'/game-assets/characters/{key}'
            return {'type': 'motion', 'motions': f'{url}/motions.json', 'face': f'{url}/face.png'}
    return None


def has_ultimate(source: Path, avatar: int) -> bool:
    """Whether the outfit's ultimate cinematic exists (the Bleach villains 77-83 have none)."""
    return any((source / ULTIMATE_DIR).glob(f'fighteffect_{ULTIMATE_BASE + avatar}*'))


def extract_art(source: Path, resource_id: str, scale: int) -> dict:
    avatar = boss_avatar(resource_id)
    if avatar is not None and (art := avatar_art(source, avatar, scale)):
        if not has_ultimate(source, avatar):
            return art
        # The boss fights with its outfit's Ultimate (config('skills.ultimate')).
        outfit = art['motions'].split('/')[3]
        return {**art, 'outfit': outfit, 'outfit_level': costume_level(resource_id)}

    key = art_id(resource_id)
    out = ASSETS / 'monsters' / key
    out.mkdir(parents=True, exist_ok=True)
    url = f'/game-assets/monsters/{key}'

    if resource_id.startswith(ANIMATED_PREFIX):
        folder = mob_folder(source / 'movieclip/motion/mob', key)
        number_id = key[1:]
        # Several floors share an opponent; build its art once per run.
        if key not in BUILT:
            BUILT.add(key)
            write_motion_sheet(key, find_motions(folder, f'motion_{number_id}_{{action}}*.swf'), out, scale)
            face = sorted((source / 'bitmap/userfaceavatar/mob').glob(f'userface_{key}.*png'))
            if face:
                shutil.copyfile(face[0], out / 'face.png')
                if scale > 1:
                    upscale_file(out / 'face.png', scale)
        return {'type': 'motion', 'motions': f'{url}/motions.json', 'face': f'{url}/face.png'}

    portrait = sorted((source / 'bitmap/npcbackphoto').glob(f'{key}.*png'))[0]
    shutil.copyfile(portrait, out / 'portrait.png')
    if scale > 1:
        upscale_file(out / 'portrait.png', scale)
    return {'type': 'portrait', 'portrait': f'{url}/portrait.png', 'face': f'{url}/portrait.png'}


def copy_backgrounds(source: Path) -> list[str]:
    """Copy the original battle backdrops; returns their URLs in a stable order."""
    out = ASSETS / 'battle' / 'backgrounds'
    out.mkdir(parents=True, exist_ok=True)
    urls = []
    for image in sorted((source / BACKGROUNDS_DIR).glob('*.jpg')):
        if image.stem in SKIPPED_BACKGROUNDS:
            continue
        shutil.copyfile(image, out / image.name)
        urls.append(f'/game-assets/battle/backgrounds/{image.name}')
    return urls


def main() -> None:
    if len(sys.argv) not in (2, 3) or sys.argv[2:] not in ([], ['--hd']):
        sys.exit(__doc__)
    scale = HD_SCALE if '--hd' in sys.argv else 1
    if scale > 1 and not upscaler_available():
        sys.exit('Real-ESRGAN not found; see tools/upscale.py.')
    source = Path(sys.argv[1]).expanduser() / SOURCE
    datatable = source / 'binary/datatable'
    npcs = latest_table(datatable, 'singlegatenpc')
    floor_exp = latest_table(datatable, 'sgategetexp')
    language = load_compressed(source / 'binary/lg/language.lg')
    # English names fill in the floors NAMES does not cover (the Akatsuki on top).
    english_files = sorted((source / 'binary/keyvaluetable').glob('language.s*.kv'), key=lambda p: int(p.name.split('.s')[1].split('.')[0]))
    english = load_compressed(english_files[-1])
    exp_by_floor = {int(order): number(exp) for order, exp in zip(floor_exp['Order'], floor_exp['Exp'])}

    backgrounds = copy_backgrounds(source)
    floors = []
    for index in range(1, len(npcs['ID'])):  # row 0 holds column descriptions
        floor = index
        resource_id = npcs['ResourceID'][index]
        chinese = language.get('lg_' + npcs['Name'][index])
        floors.append({
            'floor': floor,
            'code': npcs['ID'][index],
            'name': NAMES.get(chinese) or english.get('lg_' + npcs['Name'][index], '').strip() or UNNAMED,
            'is_boss': not resource_id.startswith(ANIMATED_PREFIX),
            **stats(npcs, index),
            # The last floor has no exp row; it reuses the previous floor's reward.
            'exp': exp_by_floor.get(floor) or exp_by_floor[max(exp_by_floor)],
            'art': {
                **extract_art(source, resource_id, scale),
                # Every ten floors move to the next original battlefield.
                'background': backgrounds[(floor - 1) // FLOORS_PER_BACKGROUND % len(backgrounds)],
            },
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

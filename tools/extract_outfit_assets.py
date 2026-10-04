#!/usr/bin/env python3
"""Extract the wearable outfits (Naruto and Bleach characters) from the Pockie Ninja backup.

Usage: python3 tools/extract_outfit_assets.py <path-to-game-pockieninja> [--hd]
--hd upscales the art 2x with Real-ESRGAN (see tools/upscale.py).

Reads avataritem (one +0 item per outfit, AvatarLevel 1: sex, ItemColor, and
Clothing, the people_<id> art it wears) and the English names
in keyvaluetable/language, and writes database/data/outfits.json (OutfitSeeder)
plus face.png and motions.{png,json} under public/game-assets/characters/<sex>_<id>/
for every outfit with art. The 18 creatable avatars are outfits too. Output is
gitignored: the source art is copyrighted and stays local.
"""

from __future__ import annotations

import json
import re
import os
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

from amf3 import load_compressed
from extract_item_assets import latest_table, number, rows
from motion import find_motions, write_motion_sheet
from upscale import available as upscaler_available
from upscale import upscale_file

SOURCE = 'apache/source'
ROOT = Path(__file__).resolve().parent.parent
ART_OUT = ROOT / 'public' / 'game-assets' / 'characters'
DATA_OUT = ROOT / 'database' / 'data' / 'outfits.json'
HD_SCALE = 2

# Original ItemColor -> rarity.
RARITIES = {0: 'grey', 1: 'blue', 2: 'orange'}
# AvatarLevel of the +0 item; +N upgrades are other rows.
BASE_LEVEL = 1
VECTOR_SYMBOL = 'MotionSource'
# The data marks Konan grey, but the original sold her in the S-rank (orange) pot.
RARITY_OVERRIDES = {'1_68': 'orange'}
# rolebase.Popsinger (keyed by avatar id) -> the weapons an outfit holds; 7 (all) is None.
WEAPON_CLASSES = {1: 'blunt', 2: 'sharp', 4: 'gloves'}
# Indonesian leftovers in the English build.
RENAMES = {'Kostum Natal': 'Christmas', ' Kostum': ''}


def outfit_name(label: str) -> str:
    """'<font color=..>Hatake Kakashi ＋0</font>' -> 'Hatake Kakashi'."""
    name = re.sub(r'<[^>]+>', '', label)
    name = re.sub(r'\s*[＋+]\d+\s*$', '', name)
    for indonesian, english in RENAMES.items():
        name = name.replace(indonesian, english)
    return name.strip()


def weapon_classes(rolebase: dict) -> dict[int, str | None]:
    """Avatar id -> weapon class, from rolebase's Popsinger column."""
    return {number(avatar): WEAPON_CLASSES.get(number(popsinger)) for avatar, popsinger in zip(rolebase['ID'][1:], rolebase['Popsinger'][1:])}


def outfits(avatar_items: list[dict], language: dict, classes: dict[int, str | None]) -> list[dict]:
    """Every base (+0) outfit with an English name, ordered by art id.

    The key is the art it wears ("<sex>_<Clothing>"). Most +0 avatars share
    their id with the art; the Shippuden ones (avatar 4003, Kakuzu) wear
    people_103, whose own label "Ggio Vega +1" belongs to an upgrade row.
    """
    found = []
    for item in avatar_items:
        avatar_id = number(item['AvatarID'])
        name = outfit_name(language.get(f'lg_avatar{avatar_id}', ''))
        if number(item['AvatarLevel']) != BASE_LEVEL or not name:
            continue
        key = f"{number(item['Sex'])}_{number(item['Clothing'])}"
        found.append({
            'key': key,
            'name': name,
            'sex': number(item['Sex']),
            'rarity': RARITY_OVERRIDES.get(key, RARITIES[number(item['ItemColor'])]),
            'weapon_class': classes.get(avatar_id),
        })
    return sorted(found, key=lambda outfit: int(outfit['key'].split('_')[1]))


def vector_motion_frames(motion_swf: Path, zoom: int):
    """(frames, ticks, fps) of a vector motion, as motion_frames gives for bitmap ones.

    JPEXS renders the MotionSource symbol (needs Java, see extract_effect_assets);
    frames are drawn at `zoom`, offsets from the character origin in those pixels.
    """
    from PIL import Image

    from extract_effect_assets import origin_of, render
    from swf import read_swf, symbol_classes

    java = os.environ.get('JAVA', '/opt/homebrew/opt/openjdk/bin/java')
    ffdec = Path(os.environ.get('FFDEC_JAR', '~/.local/opt/jpexs/ffdec.jar')).expanduser()
    swf = read_swf(motion_swf)
    with tempfile.TemporaryDirectory() as tmp:
        out = Path(tmp)
        render(java, ffdec, motion_swf, symbol_classes(swf)[VECTOR_SYMBOL], zoom, out)
        sprite = next((out / 'png').glob('DefineSprite_*')).name
        pngs = sorted((out / 'png' / sprite).glob('*.png'), key=lambda p: int(p.stem))
        if not pngs:
            raise ValueError(f'{motion_swf}: nothing rendered')
        frames = []
        for path in pngs:
            image = Image.open(path).convert('RGBA')
            ox, oy = origin_of(out / 'svg' / sprite / f'{path.stem}.svg', image.size)
            frames.append((image, -ox, -oy))
    return frames, list(range(len(frames))), swf.frame_rate


def extract_art(source: Path, key: str, scale: int) -> bool:
    """Write face and motions for one outfit; False when the backup has no art for it."""
    avatar_id = key.split('_')[1]
    faces = sorted((source / 'bitmap/userfaceavatar/people').glob(f'userface_{key}_role*.png'))
    try:
        motions = find_motions(source / f'movieclip/motion/people/people_{avatar_id}', f'motion_{key}_{{action}}_role*.swf')
    except FileNotFoundError:
        return False
    if not faces:
        return False

    out = ART_OUT / key
    out.mkdir(parents=True, exist_ok=True)
    try:
        write_motion_sheet(key, motions, out, scale)
    except ValueError:
        # Little Jun is drawn in vectors: JPEXS renders it, sharp at any scale.
        try:
            write_motion_sheet(key, motions, out, scale, frames_of=lambda swf: vector_motion_frames(swf, scale))
        except (ValueError, OSError, subprocess.CalledProcessError) as error:
            print(f'skipped {key}: {error}')
            return False
    shutil.copyfile(faces[0], out / 'face.png')
    if scale > 1:
        upscale_file(out / 'face.png', scale)
    return True


def main() -> None:
    if len(sys.argv) not in (2, 3) or sys.argv[2:] not in ([], ['--hd']):
        sys.exit(__doc__)
    scale = HD_SCALE if '--hd' in sys.argv else 1
    if scale > 1 and not upscaler_available():
        sys.exit('Real-ESRGAN not found; see tools/upscale.py.')
    source = Path(sys.argv[1]).expanduser() / SOURCE
    binary = source / 'binary'

    avatar_items = rows(load_compressed(latest_table(binary / 'datatable', 'avataritem')))
    languages = sorted((binary / 'keyvaluetable').glob('language.s*.kv'), key=lambda p: int(p.name.split('.s')[1].split('.')[0]))
    language = load_compressed(languages[-1])

    classes = weapon_classes(load_compressed(latest_table(binary / 'datatable', 'rolebase')))
    kept = [outfit for outfit in outfits(avatar_items, language, classes) if extract_art(source, outfit['key'], scale)]
    DATA_OUT.parent.mkdir(parents=True, exist_ok=True)
    DATA_OUT.write_text(json.dumps(kept, indent=1, ensure_ascii=False))
    print(f'{len(kept)} outfits -> {DATA_OUT.relative_to(ROOT)}')


if __name__ == '__main__':
    main()

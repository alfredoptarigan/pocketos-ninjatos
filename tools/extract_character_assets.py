#!/usr/bin/env python3
"""Extract the creatable ninja avatars from the Pockie Ninja backup.

Usage: python3 tools/extract_character_assets.py <path-to-game-pockieninja>
Requires Pillow (pip install pillow) to merge JPEG colour with its alpha mask.

For every avatar offered on the original create screen it writes, under
public/game-assets/characters/<sex>_<id>/:
  portrait.png  large art for the create screen
  face.png      small face icon for the HUD
  motions.png   animation frames (idle, stance, run, attack, dodge, dead)
  motions.json  Pixi spritesheet with one animation per action
Output is gitignored: the source art is copyrighted and stays local.
"""

from __future__ import annotations

import json
import shutil
import sys
from pathlib import Path

from motion import find_motions, write_motion_sheet
from swf import jpeg3_bitmaps, read_swf

SOURCE = 'apache/source'
OUT_DIR = Path(__file__).resolve().parent.parent / 'public' / 'game-assets' / 'characters'


def find_one(folder: Path, pattern: str) -> Path:
    matches = sorted(folder.glob(pattern))
    if not matches:
        raise FileNotFoundError(f'{folder}/{pattern}')
    return matches[0]


def extract_avatar(source: Path, create_swf: Path) -> str:
    # avatars_<sex>_<id>_clothing_create.s110.swf
    _, sex, avatar_id, *_ = create_swf.name.split('_')
    key = f'{sex}_{avatar_id}'
    out = OUT_DIR / key
    out.mkdir(parents=True, exist_ok=True)

    portrait = next(iter(jpeg3_bitmaps(read_swf(create_swf)).values()))
    portrait.save(out / 'portrait.png')

    face = find_one(source / 'bitmap/userfaceavatar/people', f'userface_{key}_role*.png')
    shutil.copyfile(face, out / 'face.png')

    motions = find_motions(source / f'movieclip/motion/people/people_{avatar_id}', f'motion_{key}_{{action}}_role*.swf')
    write_motion_sheet(key, motions, out)
    return f'{key}: {len(motions)} motions'


def main() -> None:
    if len(sys.argv) != 2:
        sys.exit(__doc__)
    source = Path(sys.argv[1]).expanduser() / SOURCE
    create_dir = source / 'bitmap/peoplecreate'
    if not create_dir.is_dir():
        sys.exit(f'peoplecreate folder not found: {create_dir}')

    for create_swf in sorted(create_dir.glob('avatars_*_clothing_create*.swf')):
        print(extract_avatar(source, create_swf))


if __name__ == '__main__':
    main()

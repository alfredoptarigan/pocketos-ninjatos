#!/usr/bin/env python3
"""Extract the weapons ninjas hold in battle from the Pockie Ninja backup.

Usage: python3 tools/extract_weapon_assets.py <path-to-game-pockieninja> [--hd]
--hd upscales the frames 2x with Real-ESRGAN (see tools/upscale.py).
Run after extract_outfit_assets.py and extract_equipment_assets.py: it draws
every weapon look in database/data/equipment.json for every outfit with art.

The original client lays a weapon motion over the body motion, tick for tick
and from the same origin. Each outfit only holds its own class of weapon
(rolebase.Popsinger: blunt, sharp or gloves; a few hold all three), so a
sword has no art on a gloves outfit and the ninja fights bare-handed.

Writes public/game-assets/weapons/<outfit key>/<look>/motions.{png,json}.
Output is gitignored: it is derived from copyrighted game files.
"""

from __future__ import annotations

import json
import sys
from pathlib import Path

from motion import ACTIONS, write_motion_sheet
from upscale import available as upscaler_available

ROOT = Path(__file__).resolve().parent.parent
CHARACTERS = ROOT / 'public' / 'game-assets' / 'characters'
OUT = ROOT / 'public' / 'game-assets' / 'weapons'
EQUIPMENT = ROOT / 'database' / 'data' / 'equipment.json'
WEAPON_DIR = 'apache/source/movieclip/motion/weapon'
HD_SCALE = 2


def weapon_motions(folder: Path, key: str, look: str) -> dict[str, Path]:
    """Action name -> weapon SWF of one outfit holding one look; idle and dodge have none."""
    found = {}
    for name, action in ACTIONS.items():
        # people_59 spells its gloves "glove".
        for spelling in (look, look.replace('gloves', 'glove')):
            path = folder / action / f'motion_{key}_{spelling}_{action}_weapon.swf'
            if path.is_file():
                found[name] = path
                break
    return found


def main() -> None:
    if len(sys.argv) not in (2, 3) or sys.argv[2:] not in ([], ['--hd']):
        sys.exit(__doc__)
    scale = HD_SCALE if '--hd' in sys.argv else 1
    if scale > 1 and not upscaler_available():
        sys.exit('Real-ESRGAN not found; see tools/upscale.py.')
    weapons = Path(sys.argv[1]).expanduser() / WEAPON_DIR
    looks = sorted({piece['look'] for piece in json.loads(EQUIPMENT.read_text()) if piece.get('look')})
    if not looks:
        sys.exit('No weapon looks in equipment.json; rerun extract_equipment_assets.py first.')

    written = 0
    for outfit in sorted(path.name for path in CHARACTERS.iterdir() if (path / 'motions.json').is_file()):
        folder = weapons / f"people_{outfit.split('_')[1]}"
        for look in looks:
            motions = weapon_motions(folder, outfit, look)
            if not motions:
                continue
            out = OUT / outfit / look
            out.mkdir(parents=True, exist_ok=True)
            try:
                write_motion_sheet(f'{outfit}_{look}', motions, out, scale)
            except ValueError as error:  # vector-only stubs
                print(f'skipped {outfit} {look}: {error}')
                continue
            written += 1

    print(f'Wrote {written} weapon sheets to {OUT.relative_to(ROOT)}')


if __name__ == '__main__':
    main()

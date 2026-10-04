#!/usr/bin/env python3
"""Extract the original skill icons from the Pockie Ninja backup.

Usage: python3 tools/extract_skill_assets.py <path-to-game-pockieninja> [--hd]
--hd AI-upscales the icons 4x (see tools/upscale.py).

Writes public/game-assets/skills/<skill id>.png for every skill-panel skill
and ultimate (clientskill Type 1 and 2), and the battle status icons (bitmap/icon/buff, picked
by hand in STATUS_ICONS) to public/game-assets/statuses/<status>.png. Skill
rules live in config/skills.php; this script only provides art. Output is
gitignored.
"""

from __future__ import annotations

import shutil
import sys
from pathlib import Path

from amf3 import load_compressed
from upscale import available as upscaler_available
from upscale import upscale_all

OUT_DIR = Path(__file__).resolve().parent.parent / 'public' / 'game-assets' / 'skills'
STATUS_DIR = OUT_DIR.parent / 'statuses'
# Battle status (BattleSimulator) -> original buff icon number (icon_buff<n>).
STATUS_ICONS = {
    'burn': 11804, 'drunk': 11806, 'freeze': 11802, 'slow': 11801, 'shield': 11831,
    'invulnerable': 11835, 'poison': 11811, 'seal': 11812, 'dead_demon': 11833,
    'bloodboil': 11814, 'charm': 11815, 'snare': 11816, 'clay': 11820, 'prison': 11817,
    'mirage': 11836, 'regen': 11832, 'chakra_burn': 11813, 'gates': 11819,
    'cloud': 11827, 'mist': 11821, 'sunset': 11822, 'cursed_seal': 11829,
}
SOURCE = 'apache/source'
ICON_TYPES = {'1', '2'}  # clientskill Type: 1 skill panel, 2 ultimate, 3 pet, 4 stage
HEADER_ROW = 1
HD_ICON_SCALE = 4


def latest_table(datatable: Path, name: str) -> dict:
    versions = sorted(datatable.glob(f'{name}.s*.tab'), key=lambda p: int(p.name.split('.s')[1].split('.')[0]))
    return load_compressed(versions[-1])


def main() -> None:
    if len(sys.argv) not in (2, 3) or sys.argv[2:] not in ([], ['--hd']):
        sys.exit(__doc__)
    hd = '--hd' in sys.argv
    if hd and not upscaler_available():
        sys.exit('Real-ESRGAN not found; see tools/upscale.py.')

    source = Path(sys.argv[1]).expanduser() / SOURCE
    skills = latest_table(source / 'binary/datatable', 'clientskill')
    icon_dir = source / 'bitmap/icon/skill'
    OUT_DIR.mkdir(parents=True, exist_ok=True)

    icons = {}
    for index in range(HEADER_ROW, len(skills['FakeID'])):
        if skills['Type'][index] not in ICON_TYPES:
            continue
        matches = sorted(icon_dir.glob(f"{skills['ResourceID'][index].lower()}.*png"))
        if matches:
            icons[f"{skills['FakeID'][index]}.png"] = matches[0]

    if hd:
        from PIL import Image

        images = {name: Image.open(path).convert('RGBA') for name, path in icons.items()}
        for name, image in upscale_all(images, HD_ICON_SCALE).items():
            image.save(OUT_DIR / name)
    else:
        for name, path in icons.items():
            shutil.copyfile(path, OUT_DIR / name)

    print(f'Wrote {len(icons)} skill icons to {OUT_DIR.relative_to(OUT_DIR.parents[2])}')

    from PIL import Image

    STATUS_DIR.mkdir(parents=True, exist_ok=True)
    buffs = source / 'bitmap/icon/buff'
    for status, number in STATUS_ICONS.items():
        Image.open(next(buffs.glob(f'icon_buff{number}.*'))).convert('RGBA').save(STATUS_DIR / f'{status}.png')
    print(f'Wrote {len(STATUS_ICONS)} status icons to {STATUS_DIR.relative_to(OUT_DIR.parents[2])}')


if __name__ == '__main__':
    main()

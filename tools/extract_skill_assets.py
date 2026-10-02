#!/usr/bin/env python3
"""Extract the original skill icons from the Pockie Ninja backup.

Usage: python3 tools/extract_skill_assets.py <path-to-game-pockieninja> [--hd]
--hd AI-upscales the icons 4x (see tools/upscale.py).

Writes public/game-assets/skills/<skill id>.png for every skill-panel skill
(clientskill Type 1). Skill rules live in config/game.php ('skills'); this
script only provides art. Output is gitignored.
"""

from __future__ import annotations

import shutil
import sys
from pathlib import Path

from amf3 import load_compressed
from upscale import available as upscaler_available
from upscale import upscale_all

OUT_DIR = Path(__file__).resolve().parent.parent / 'public' / 'game-assets' / 'skills'
SOURCE = 'apache/source'
PANEL_SKILL = '1'  # clientskill Type: 1 skill panel, 2 ultimate, 3 pet, 4 stage
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
        if skills['Type'][index] != PANEL_SKILL:
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


if __name__ == '__main__':
    main()

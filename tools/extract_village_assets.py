#!/usr/bin/env python3
"""Extract village backgrounds and building hotspots from the Pockie Ninja backup.

Usage: python3 tools/extract_village_assets.py <path-to-game-pockieninja>

Writes public/game-assets/villages/<id>.jpg and public/game-assets/villages.json.
Output is gitignored: the source art is copyrighted and stays local.
"""

from __future__ import annotations

import json
import sys
from pathlib import Path

from swf import first_jpeg, read_swf, top_level_placements

SCENE_DIR = 'apache/source/movieclip/scene/maincity'
OUT_DIR = Path(__file__).resolve().parent.parent / 'public' / 'game-assets'


def building_hotspots(labels_swf: Path) -> dict[str, dict[str, int]]:
    """Named label clips (clip_<building>_name) and their positions."""
    return {
        p.name.removeprefix('clip_').removesuffix('_name'): {'x': round(p.x), 'y': round(p.y)}
        for p in top_level_placements(read_swf(labels_swf))
        if p.name and p.x is not None
    }


def main() -> None:
    if len(sys.argv) != 2:
        sys.exit(__doc__)
    scene_dir = Path(sys.argv[1]).expanduser() / SCENE_DIR
    if not scene_dir.is_dir():
        sys.exit(f'Folder scene tidak ditemukan: {scene_dir}')

    (OUT_DIR / 'villages').mkdir(parents=True, exist_ok=True)
    villages = {}
    for background in sorted(scene_dir.glob('background_*.swf')):
        village_id = background.name.split('.')[0].removeprefix('background_')
        jpeg = first_jpeg(read_swf(background))
        (OUT_DIR / 'villages' / f'{village_id}.jpg').write_bytes(jpeg)

        labels = scene_dir / f'buildname_{village_id}.swf'
        buildings = building_hotspots(labels) if labels.exists() else {}
        villages[village_id] = {
            'background': f'/game-assets/villages/{village_id}.jpg',
            'buildings': buildings,
        }
        print(f'{village_id}: {len(buildings)} bangunan')

    (OUT_DIR / 'villages.json').write_text(json.dumps(villages, indent=2))


if __name__ == '__main__':
    main()

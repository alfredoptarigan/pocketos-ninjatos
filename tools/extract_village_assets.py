#!/usr/bin/env python3
"""Extract village backgrounds and clickable buildings from the Pockie Ninja backup.

Usage: python3 tools/extract_village_assets.py <path-to-game-pockieninja>
Requires Pillow (pip install pillow) to compose the building overlays.

Writes, under public/game-assets/:
  villages/<id>.jpg               1920x1080 background
  villages/<id>/<building>.png    building cut-out, used for hover and hit testing
  villages.json                   {id: {background, buildings: {key: {image, x, y}}}}
Output is gitignored: the source art is copyrighted and stays local.
"""

from __future__ import annotations

import json
import sys
from pathlib import Path

from swf import all_bitmaps, first_jpeg, placed_bitmaps, read_swf, top_level_placements

SCENE_DIR = 'apache/source/movieclip/scene/maincity'
OUT_DIR = Path(__file__).resolve().parent.parent / 'public' / 'game-assets'
CLIP_PREFIX = 'clip_'


def latest(scene_dir: Path, pattern: str) -> Path | None:
    """Scene files may ship in several versions (name.s<version>.swf); take the newest."""
    def version(path: Path) -> int:
        parts = path.name.split('.')
        return int(parts[1][1:]) if len(parts) > 2 and parts[1].startswith('s') else 0

    matches = sorted(scene_dir.glob(pattern), key=version)
    return matches[-1] if matches else None


def extract_buildings(element_swf: Path, out_dir: Path, url_prefix: str) -> dict[str, dict]:
    """Compose each named clip's bitmaps into one PNG cut-out."""
    from PIL import Image

    swf = read_swf(element_swf)
    bitmaps = all_bitmaps(swf)
    buildings = {}
    for placement in top_level_placements(swf):
        if not (placement.name or '').startswith(CLIP_PREFIX) or placement.character_id is None:
            continue
        parts = [
            (bitmaps[bitmap_id], x, y)
            for bitmap_id, x, y in placed_bitmaps(swf, placement.character_id, placement.x or 0, placement.y or 0)
            if bitmap_id in bitmaps
        ]
        if not parts:
            continue

        left = min(x for _, x, _ in parts)
        top = min(y for _, _, y in parts)
        width = round(max(x + image.width for image, x, _ in parts) - left)
        height = round(max(y + image.height for image, _, y in parts) - top)
        cutout = Image.new('RGBA', (width, height))
        for image, x, y in parts:
            cutout.alpha_composite(image, (round(x - left), round(y - top)))

        key = placement.name.removeprefix(CLIP_PREFIX)
        cutout.save(out_dir / f'{key}.png')
        buildings[key] = {'image': f'{url_prefix}/{key}.png', 'x': round(left), 'y': round(top)}
    return buildings


def main() -> None:
    if len(sys.argv) != 2:
        sys.exit(__doc__)
    scene_dir = Path(sys.argv[1]).expanduser() / SCENE_DIR
    if not scene_dir.is_dir():
        sys.exit(f'Scene folder not found: {scene_dir}')

    villages = {}
    for background in sorted(scene_dir.glob('background_*.swf')):
        village_id = background.name.split('.')[0].removeprefix('background_')
        village_dir = OUT_DIR / 'villages' / village_id
        village_dir.mkdir(parents=True, exist_ok=True)
        (OUT_DIR / 'villages' / f'{village_id}.jpg').write_bytes(first_jpeg(read_swf(background)))

        element = latest(scene_dir, f'element_{village_id}*.swf')
        url_prefix = f'/game-assets/villages/{village_id}'
        buildings = extract_buildings(element, village_dir, url_prefix) if element else {}
        villages[village_id] = {'background': f'/game-assets/villages/{village_id}.jpg', 'buildings': buildings}
        print(f'{village_id}: {len(buildings)} buildings ({", ".join(buildings)})')

    (OUT_DIR / 'villages.json').write_text(json.dumps(villages, indent=2))


if __name__ == '__main__':
    main()

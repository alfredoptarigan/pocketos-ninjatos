#!/usr/bin/env python3
"""Extract the creatable ninja avatars from the Pockie Ninja backup.

Usage: python3 tools/extract_character_assets.py <path-to-game-pockieninja>
Requires Pillow (pip install pillow) to merge JPEG colour with its alpha mask.

For every avatar offered on the original create screen it writes, under
public/game-assets/characters/<sex>_<id>/:
  portrait.png  large art for the create screen
  face.png      small face icon for the HUD
  idle.png      idle animation frames, side by side
  idle.json     Pixi spritesheet for idle.png (animation "idle")
Output is gitignored: the source art is copyrighted and stays local.
"""

from __future__ import annotations

import json
import shutil
import sys
from pathlib import Path

from swf import jpeg3_bitmaps, read_swf, shape_bitmap_fills, sprite_timelines

SOURCE = 'apache/source'
OUT_DIR = Path(__file__).resolve().parent.parent / 'public' / 'game-assets' / 'characters'
IDLE_ACTION = '1'  # motion_<sex>_<id>_1_role.swf is the standing idle loop


def find_one(folder: Path, pattern: str) -> Path:
    matches = sorted(folder.glob(pattern))
    if not matches:
        raise FileNotFoundError(f'{folder}/{pattern}')
    return matches[0]


def idle_frames(motion_swf: Path):
    """Return (frames, per-tick frame indexes, fps) of the idle animation.

    frames are (image, x, y) with x/y the bitmap offset from the character origin.
    """
    swf = read_swf(motion_swf)
    bitmaps = jpeg3_bitmaps(swf)
    fills = shape_bitmap_fills(swf)
    timeline = max(sprite_timelines(swf).values(), key=len)

    frames, index_of_shape, ticks = [], {}, []
    for stage in timeline:
        shape_id = next((cid for cid in stage.values() if cid in fills and fills[cid][0] in bitmaps), None)
        if shape_id is None:
            continue
        if shape_id not in index_of_shape:
            bitmap_id, x, y = fills[shape_id]
            index_of_shape[shape_id] = len(frames)
            frames.append((bitmaps[bitmap_id], x, y))
        ticks.append(index_of_shape[shape_id])

    if not frames:
        raise ValueError(f'{motion_swf}: no bitmap frames found')
    return frames, ticks, swf.frame_rate


def write_idle_sheet(key: str, frames, ticks, fps: float, out: Path) -> None:
    """Pad every frame to the shared bounds so one anchor fits all, then pack.

    Frame names carry the avatar key: Pixi caches textures globally by name.
    """
    from PIL import Image

    left = min(x for _, x, _ in frames)
    top = min(y for _, _, y in frames)
    width = round(max(x + image.width for image, x, _ in frames) - left)
    height = round(max(y + image.height for image, _, y in frames) - top)

    sheet = Image.new('RGBA', (width * len(frames), height))
    frame_data = {}
    for index, (image, x, y) in enumerate(frames):
        sheet.alpha_composite(image, (index * width + round(x - left), round(y - top)))
        frame_data[f'{key}_idle_{index}'] = {
            'frame': {'x': index * width, 'y': 0, 'w': width, 'h': height},
            'sourceSize': {'w': width, 'h': height},
            'spriteSourceSize': {'x': 0, 'y': 0, 'w': width, 'h': height},
            # The SWF origin sits at the character's ground point.
            'anchor': {'x': -left / width, 'y': -top / height},
        }
    sheet.save(out / 'idle.png')

    spritesheet = {
        'frames': frame_data,
        'animations': {'idle': [f'{key}_idle_{i}' for i in ticks]},
        'meta': {'image': 'idle.png', 'size': {'w': sheet.width, 'h': sheet.height}, 'scale': 1, 'fps': fps},
    }
    (out / 'idle.json').write_text(json.dumps(spritesheet, indent=2))


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

    motion = find_one(source / f'movieclip/motion/people/people_{avatar_id}', f'motion_{key}_{IDLE_ACTION}_role*.swf')
    frames, ticks, fps = idle_frames(motion)
    write_idle_sheet(key, frames, ticks, fps, out)
    return f'{key}: {len(frames)} frame idle, {len(ticks)} tick @ {fps:g} fps'


def main() -> None:
    if len(sys.argv) != 2:
        sys.exit(__doc__)
    source = Path(sys.argv[1]).expanduser() / SOURCE
    create_dir = source / 'bitmap/peoplecreate'
    if not create_dir.is_dir():
        sys.exit(f'Folder peoplecreate tidak ditemukan: {create_dir}')

    for create_swf in sorted(create_dir.glob('avatars_*_clothing_create*.swf')):
        print(extract_avatar(source, create_swf))


if __name__ == '__main__':
    main()

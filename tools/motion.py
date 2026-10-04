"""Turn original motion SWFs (bitmap frames on a sprite timeline) into Pixi spritesheets."""

from __future__ import annotations

import json
from pathlib import Path

from swf import all_bitmaps, read_swf, shape_bitmap_fills, sprite_timelines

# Action ids shared by player and monster motion files, verified by rendering them.
ACTIONS = {
    'idle': '1',      # relaxed idle (village, create screen)
    'stance': '999',  # battle stance
    'run': '55',      # dash towards the target
    'attack': '52',   # normal attack
    'dodge': '2',     # back-flip used for misses
    'dead': '100',    # knocked out
}
# Some monsters ship without these; the client falls back to 'stance'.
OPTIONAL_ACTIONS = {'idle', 'dodge'}
MAX_SHEET_WIDTH = 4096  # stay within WebGL texture limits on every GPU


def motion_frames(motion_swf: Path):
    """Return (frames, per-tick frame indexes, fps) of one motion SWF.

    frames are (image, x, y) with x/y the bitmap offset from the character origin.
    """
    swf = read_swf(motion_swf)
    bitmaps = all_bitmaps(swf)
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


def write_motion_sheet(key: str, motions: dict[str, Path], out: Path, scale: int = 1, frames_of=None) -> None:
    """Pack every action into motions.png + motions.json (one shared anchor).

    Frame names carry `key` because Pixi caches textures globally by name.
    With scale > 1 frames are AI-upscaled (tools/upscale.py) and the sheet's
    meta.scale tells Pixi to draw them at their original size, only sharper.
    `frames_of` replaces motion_frames for art drawn already at `scale`
    (vector motions rendered by JPEXS), which then needs no AI.
    """
    from PIL import Image

    actions = {name: (frames_of or motion_frames)(path) for name, path in motions.items()}
    if scale > 1 and frames_of is None:
        actions = upscale_actions(actions, scale)
    every = [frame for frames, _, _ in actions.values() for frame in frames]
    left = min(x for _, x, _ in every)
    top = min(y for _, _, y in every)
    width = round(max(x + image.width for image, x, _ in every) - left)
    height = round(max(y + image.height for image, _, y in every) - top)
    anchor = {'x': -left / width, 'y': -top / height}  # SWF origin = character ground point

    columns = max(1, min(len(every), MAX_SHEET_WIDTH // width))
    rows = (len(every) + columns - 1) // columns
    sheet = Image.new('RGBA', (width * columns, height * rows))
    frame_data, animations, fps, slot = {}, {}, {}, 0
    for action, (frames, ticks, rate) in actions.items():
        names = []
        for index, (image, x, y) in enumerate(frames):
            cx, cy = (slot % columns) * width, (slot // columns) * height
            sheet.alpha_composite(image, (cx + round(x - left), cy + round(y - top)))
            name = f'{key}_{action}_{index}'
            frame_data[name] = {
                'frame': {'x': cx, 'y': cy, 'w': width, 'h': height},
                'sourceSize': {'w': width, 'h': height},
                'spriteSourceSize': {'x': 0, 'y': 0, 'w': width, 'h': height},
                'anchor': anchor,
            }
            names.append(name)
            slot += 1
        animations[action] = [names[i] for i in ticks]
        fps[action] = rate

    sheet.save(out / 'motions.png')
    (out / 'motions.json').write_text(json.dumps({
        'frames': frame_data,
        'animations': animations,
        'meta': {'image': 'motions.png', 'size': {'w': sheet.width, 'h': sheet.height}, 'scale': scale, 'fps': fps},
    }))


def upscale_actions(actions: dict, scale: int) -> dict:
    """Upscale every frame in one model run; offsets scale with the bitmaps."""
    from upscale import upscale_all

    flat = {(name, index): image for name, (frames, _, _) in actions.items() for index, (image, _, _) in enumerate(frames)}
    bigger = upscale_all(flat, scale)
    return {
        name: ([(bigger[(name, index)], x * scale, y * scale) for index, (_, x, y) in enumerate(frames)], ticks, rate)
        for name, (frames, ticks, rate) in actions.items()
    }


def find_motions(folder: Path, pattern: str) -> dict[str, Path]:
    """Map action name -> SWF, `pattern` containing `{action}`, e.g. 'motion_32051_{action}*.swf'."""
    found = {}
    for name, action_id in ACTIONS.items():
        matches = sorted(folder.glob(pattern.format(action=action_id)))
        # glob 'motion_1*' would also match 'motion_100'; keep exact ids only.
        exact = [m for m in matches if m.name.split('.')[0].removesuffix('_role').split('_')[-1] == action_id]
        if exact:
            found[name] = exact[0]
        elif name not in OPTIONAL_ACTIONS:
            raise FileNotFoundError(f'{folder}/{pattern.format(action=action_id)}')
    return found

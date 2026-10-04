#!/usr/bin/env python3
"""Import a custom character (frames drawn by an artist or an image AI) as a new outfit.

Usage:
  python3 tools/import_custom_character.py <frames-folder> --name "Hero" --sex 0
      [--rarity orange] [--weapon sharp|blunt|gloves|all] [--key 0_201]
      [--fps attack=24 ...] [--green]

<frames-folder> holds one folder per action with its frames as PNGs, in name
order, plus face.png:
  stance/  (required)  idle/  run/  attack/  dodge/  dead/  face.png

Draw every frame on the same canvas, facing LEFT, with the feet on the same
ground line (docs/custom-character-prompts.md). The first stance frame sets
the scale (body BODY_HEIGHT px tall, like the original outfits), the ground
line (anchored FEET_BELOW_ORIGIN px above the soles, as the originals are) and
the centre; every other frame keeps its place on the canvas, so
lunges and jumps stay where they were drawn. Missing actions fall back to
stance in the game. --green keys out a #00FF00 background.

Writes public/game-assets/characters/<key>/{motions.png,motions.json,face.png}
(HD sheet, meta.scale 2, like the extracted outfits), the face again as the icon
of its ultimate (public/game-assets/skills/<1900 + id>.png), and adds or updates the
outfit in database/data/custom_outfits.json (OutfitSeeder). Then run:
  php artisan db:seed --class=OutfitSeeder
"""

from __future__ import annotations

import argparse
import json
import shutil
from dataclasses import dataclass
from pathlib import Path

from motion import ACTIONS, write_motion_sheet

ROOT = Path(__file__).resolve().parent.parent
ART_OUT = ROOT / 'public' / 'game-assets' / 'characters'
SKILL_ICONS = ROOT / 'public' / 'game-assets' / 'skills'
ULTIMATE_BASE = 1900  # config('skills.ultimate.id_base'): the outfit's ultimate id is this + its id
DATA_OUT = ROOT / 'database' / 'data' / 'custom_outfits.json'
BODY_HEIGHT = 102  # px at 1x: the median stance height of the original outfits
# The original sheets anchor a character 32 px (1x) above its soles; the stage
# stands every fighter on that point, so custom ones must match or they float.
FEET_BELOW_ORIGIN = 32
HD_SCALE = 2  # sheets hold 2x pixels, drawn at half size (sharp on retina)
FACE_SIZE = 164  # face.png of the HD outfits
DEFAULT_FPS = 12
FIRST_CUSTOM_ID = 201  # original outfits use 1-114
WEAPON_CLASSES = {'sharp': 'sharp', 'blunt': 'blunt', 'gloves': 'gloves', 'all': None}
RARITIES = ('grey', 'blue', 'orange')
GREEN_TOLERANCE = 80


@dataclass(frozen=True)
class Origin:
    """Where the character stands on the canvas (as ratios of its size) and how much to scale it."""

    x_ratio: float
    y_ratio: float
    factor: float

    @classmethod
    def of(cls, first_stance) -> Origin:
        left, top, right, bottom = first_stance.getbbox()
        factor = BODY_HEIGHT * HD_SCALE / (bottom - top)
        return cls(
            x_ratio=(left + right) / 2 / first_stance.width,
            y_ratio=(bottom - FEET_BELOW_ORIGIN * HD_SCALE / factor) / first_stance.height,
            factor=factor,
        )


def frames(images: list, origin: Origin) -> list[tuple]:
    """(image, x, y) per frame as motion_frames gives them: trimmed, scaled, offset from the feet."""
    from PIL import Image

    placed = []
    for image in images:
        box = image.getbbox() or (0, 0, 1, 1)
        crop = image.crop(box)
        crop = crop.resize((max(1, round(crop.width * origin.factor)), max(1, round(crop.height * origin.factor))), Image.LANCZOS)
        ox, oy = image.width * origin.x_ratio, image.height * origin.y_ratio
        placed.append((crop, (box[0] - ox) * origin.factor, (box[1] - oy) * origin.factor))
    return placed


def key_out_green(image):
    """A flat #00FF00 background becomes transparent."""
    keyed = image.convert('RGBA')
    keyed.putdata([
        (r, g, b, 0) if g > 255 - GREEN_TOLERANCE and r < GREEN_TOLERANCE and b < GREEN_TOLERANCE else (r, g, b, a)
        for r, g, b, a in keyed.getdata()
    ])
    return keyed


def custom_entry(data: Path, name: str, sex: int, rarity: str, weapon: str, key: str | None) -> dict:
    """The outfit row; a new character gets the next free id from FIRST_CUSTOM_ID."""
    existing = json.loads(data.read_text()) if data.is_file() else []
    if key is None:
        taken = [int(row['key'].split('_')[1]) for row in existing]
        key = f'{sex}_{max([FIRST_CUSTOM_ID - 1, *taken]) + 1}'
    return {'key': key, 'name': name, 'sex': sex, 'rarity': rarity, 'weapon_class': WEAPON_CLASSES[weapon]}


def ultimate_id(key: str) -> int:
    """'0_201' -> 2101, the id App\\Game\\Ultimate gives the outfit."""
    return ULTIMATE_BASE + int(key.split('_')[1])


def save_entry(data: Path, entry: dict) -> None:
    existing = json.loads(data.read_text()) if data.is_file() else []
    rows = [row for row in existing if row['key'] != entry['key']] + [entry]
    data.parent.mkdir(parents=True, exist_ok=True)
    data.write_text(json.dumps(sorted(rows, key=lambda row: int(row['key'].split('_')[1])), indent=1, ensure_ascii=False))


def load(folder: Path, green: bool) -> list:
    from PIL import Image

    images = [Image.open(path).convert('RGBA') for path in sorted(folder.glob('*.png'))]
    return [key_out_green(image) for image in images] if green else images


def save_face(source: Path, out: Path, green: bool) -> None:
    """face.png: the portrait trimmed and centred on a FACE_SIZE square."""
    from PIL import Image

    face = Image.open(source).convert('RGBA')
    face = key_out_green(face) if green else face
    face = face.crop(face.getbbox() or (0, 0, face.width, face.height))
    face.thumbnail((FACE_SIZE, FACE_SIZE), Image.LANCZOS)
    square = Image.new('RGBA', (FACE_SIZE, FACE_SIZE))
    square.alpha_composite(face, ((FACE_SIZE - face.width) // 2, (FACE_SIZE - face.height) // 2))
    square.save(out / 'face.png')


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument('folder', type=Path)
    parser.add_argument('--name', required=True)
    parser.add_argument('--sex', type=int, choices=(0, 1), required=True)
    parser.add_argument('--rarity', choices=RARITIES, default='orange')
    parser.add_argument('--weapon', choices=tuple(WEAPON_CLASSES), default='all')
    parser.add_argument('--key', help='update this outfit instead of adding a new one, e.g. 0_201')
    parser.add_argument('--fps', nargs='*', default=[], help='per action, e.g. attack=24 stance=24')
    parser.add_argument('--green', action='store_true', help='key out a #00FF00 background')
    args = parser.parse_args()

    actions = {name: args.folder / name for name in ACTIONS if any((args.folder / name).glob('*.png'))}
    face = args.folder / 'face.png'
    if 'stance' not in actions:
        parser.error(f'{args.folder}/stance/ needs at least one PNG frame')
    if not face.is_file():
        parser.error(f'{face} is missing')
    if args.key and args.key.split('_')[0] != str(args.sex):
        parser.error(f'--key {args.key} does not match --sex {args.sex}')

    fps = {name: DEFAULT_FPS for name in actions} | {k: float(v) for k, v in (item.split('=') for item in args.fps)}
    origin = Origin.of(load(actions['stance'], args.green)[0])
    entry = custom_entry(DATA_OUT, args.name, args.sex, args.rarity, args.weapon, args.key)
    out = ART_OUT / entry['key']
    out.mkdir(parents=True, exist_ok=True)

    def frames_of(folder: Path):
        placed = frames(load(folder, args.green), origin)
        return placed, list(range(len(placed))), fps[folder.name]

    write_motion_sheet(entry['key'], actions, out, HD_SCALE, frames_of=frames_of)
    save_face(face, out, args.green)
    # No ultimate cinematic exists for it; its Secret Technique at least shows the face as its icon.
    SKILL_ICONS.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(out / 'face.png', SKILL_ICONS / f"{ultimate_id(entry['key'])}.png")
    save_entry(DATA_OUT, entry)
    print(f"{entry['name']} ({entry['key']}): {', '.join(actions)} -> {out.relative_to(ROOT)}")
    print('Now run: php artisan db:seed --class=OutfitSeeder')


if __name__ == '__main__':
    main()

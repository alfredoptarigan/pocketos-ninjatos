#!/usr/bin/env python3
"""Extract the original jutsu (skill) battle effects from the Pockie Ninja backup.

Usage: python3 tools/extract_effect_assets.py <path-to-game-pockieninja> [--hd]
--hd renders the vector effects at 2x (JPEXS zoom, no AI needed).

Needs JPEXS + Java, like tools/extract_ui_assets.py:
  JAVA       java binary   (default /opt/homebrew/opt/openjdk/bin/java)
  FFDEC_JAR  ffdec.jar     (default ~/.local/opt/jpexs/ffdec.jar)

For every skill-panel jutsu (clientskill Type 1) it reads the FightEffect_<id>*
rows of effectconfig, renders the 'MotionEffectSource' symbol of the matching
movieclip/fighteffect SWF and packs its frames into a Pixi spritesheet:

  public/game-assets/effects/<effect>.png / .json   (anchor = SWF origin)
  public/game-assets/effects/index.json             skill id -> effects to play

Effects are drawn facing left (towards the opponent of a right-hand player),
like the character motions. Output is gitignored.
"""

from __future__ import annotations

import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

from amf3 import load_compressed
from swf import read_swf, symbol_classes

OUT_DIR = Path(__file__).resolve().parent.parent / 'public' / 'game-assets' / 'effects'
URL_DIR = '/game-assets/effects'
SOURCE = 'apache/source'
EFFECT_DIR = 'movieclip/fighteffect'
SYMBOL = 'MotionEffectSource'
PANEL_SKILL = '1'  # clientskill Type 1 = skill panel
HEADER_ROW = 1
MAX_SHEET_WIDTH = 4096
HD_ZOOM = 2
FIGHT_EFFECT = re.compile(r'^FightEffect_(\d{4})(?:_\w+)?$')


def latest_table(datatable: Path, name: str) -> dict:
    versions = sorted(datatable.glob(f'{name}.s*.tab'), key=lambda p: int(p.name.split('.s')[1].split('.')[0]))
    return load_compressed(versions[-1])


def start_of(play_time: str) -> int | str:
    """effectconfig PlayEffectTime: MotionStart, ByAttacking (on impact) or a delay in ms."""
    if play_time == 'ByAttacking':
        return 'hit'
    return int(play_time) if play_time.isdigit() else 0


def find_swf(effect_dir: Path, effect_id: str, source_id: str) -> Path | None:
    """FightEffect_18071 lives in fighteffect_1807_1.*; FightEffect_3826_M in fighteffect_3826_m.*."""
    digits = source_id.removeprefix('FightEffect_')
    stems = [f'fighteffect_{digits}', f'fighteffect_{digits[:4]}_{digits[4:]}', effect_id.lower()]
    for stem in stems:
        matches = sorted(effect_dir.glob(f'{stem}.*swf'))
        if matches:
            return matches[0]
    return None


def effect_rows(source: Path) -> list[dict]:
    datatable = source / 'binary/datatable'
    skills = latest_table(datatable, 'clientskill')
    panel = {str(skills['FakeID'][i]) for i in range(HEADER_ROW, len(skills['FakeID'])) if skills['Type'][i] == PANEL_SKILL}
    table = latest_table(datatable, 'effectconfig')
    rows = []
    for index in range(HEADER_ROW, len(table['EffectID'])):
        effect_id = str(table['EffectID'][index])
        match = FIGHT_EFFECT.match(effect_id)
        if not match or match.group(1) not in panel:
            continue
        swf = find_swf(source / EFFECT_DIR, effect_id, str(table['EffectSourceID'][index]))
        if swf is None:
            print(f'skip {effect_id}: no SWF')
            continue
        rows.append({
            'skill': match.group(1),
            'key': effect_id.lower(),
            'swf': swf,
            'type': 'beaten' if table['EffectType'][index] == 'Beaten' else 'attack',
            'layer': 'under' if table['LayoutIndex'][index] == 'Under' else 'before',
            'start': start_of(str(table['PlayEffectTime'][index])),
        })
    return rows


def render(java: str, ffdec: Path, swf: Path, symbol_id: int, zoom: int, out: Path) -> None:
    for fmt in ('png', 'svg'):
        subprocess.run(
            [
                java, '-Djava.awt.headless=true', '-jar', str(ffdec), '-zoom', str(zoom),
                '-selectid', str(symbol_id),
                '-format', f'sprite:{fmt}', '-export', 'sprite', str(out / fmt), str(swf),
            ],
            check=True,
            capture_output=True,
        )


def origin_of(svg: Path, png_size: tuple[int, int]) -> tuple[float, float]:
    """JPEXS writes the symbol origin as the root translate of the SVG; the PNG adds an even filter margin."""
    text = svg.read_text()
    width, height = (float(re.search(rf'{axis}="([\d.]+)px"', text).group(1)) for axis in ('width', 'height'))
    x, y = (float(v) for v in re.search(r'matrix\([^,]+,[^,]+,[^,]+,[^,]+, ([-\d.]+), ([-\d.]+)\)', text).groups())
    return x + (png_size[0] - width) / 2, y + (png_size[1] - height) / 2


def write_sheet(key: str, frames_dir: Path, svg_dir: Path, zoom: float, fps: float) -> None:
    """Pack trimmed frames shelf-style; every frame keeps the full-size anchor at the SWF origin."""
    from PIL import Image

    paths = sorted(frames_dir.glob('*.png'), key=lambda p: int(p.stem))
    images = [Image.open(path).convert('RGBA') for path in paths]
    width, height = images[0].size
    ox, oy = origin_of(svg_dir / '1.svg', (width, height))
    # Halve huge effects until the sheet fits a 4096 texture.
    while (placed := pack(images))[-1] > MAX_SHEET_WIDTH:
        images = [image.resize((max(1, image.width // 2), max(1, image.height // 2))) for image in images]
        width, height, ox, oy, zoom = width / 2, height / 2, ox / 2, oy / 2, zoom / 2
    placed = placed[0]

    sheet = Image.new('RGBA', (max(px + c.width for c, _, px, _ in placed), max(py + c.height for c, _, _, py in placed)))
    frame_data, names = {}, []
    for index, (crop, box, px, py) in enumerate(placed):
        sheet.alpha_composite(crop, (px, py))
        name = f'{key}_{index}'
        frame_data[name] = {
            'frame': {'x': px, 'y': py, 'w': crop.width, 'h': crop.height},
            'trimmed': True,
            'spriteSourceSize': {'x': box[0], 'y': box[1], 'w': crop.width, 'h': crop.height},
            'sourceSize': {'w': round(width), 'h': round(height)},
            'anchor': {'x': ox / width, 'y': oy / height},
        }
        names.append(name)

    sheet.save(OUT_DIR / f'{key}.png')
    (OUT_DIR / f'{key}.json').write_text(json.dumps({
        'frames': frame_data,
        'animations': {'effect': names},
        'meta': {'image': f'{key}.png', 'size': {'w': sheet.width, 'h': sheet.height}, 'scale': zoom, 'fps': fps},
    }))


def pack(images: list) -> tuple[list, int]:
    """Shelf-pack trimmed frames into rows of MAX_SHEET_WIDTH; returns placements and the sheet height."""
    placed, x, y, shelf = [], 0, 0, 0
    for image in images:
        box = image.getbbox() or (0, 0, 1, 1)
        crop = image.crop(box)
        if x + crop.width > MAX_SHEET_WIDTH:
            x, y, shelf = 0, y + shelf, 0
        placed.append((crop, box, x, y))
        x, shelf = x + crop.width, max(shelf, crop.height)
    return placed, y + shelf


def main() -> None:
    if len(sys.argv) not in (2, 3) or sys.argv[2:] not in ([], ['--hd']):
        sys.exit(__doc__)
    zoom = HD_ZOOM if '--hd' in sys.argv else 1
    java = os.environ.get('JAVA', '/opt/homebrew/opt/openjdk/bin/java')
    ffdec = Path(os.environ.get('FFDEC_JAR', '~/.local/opt/jpexs/ffdec.jar')).expanduser()
    if not ffdec.is_file() or not shutil.which(java):
        sys.exit(f'JPEXS or Java not found (FFDEC_JAR={ffdec}, JAVA={java}). See the module docstring.')

    source = Path(sys.argv[1]).expanduser() / SOURCE
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    index: dict[str, list[dict]] = {}
    for row in effect_rows(source):
        swf = read_swf(row['swf'])
        with tempfile.TemporaryDirectory() as tmp:
            out = Path(tmp)
            render(java, ffdec, row['swf'], symbol_classes(swf)[SYMBOL], zoom, out)
            sprite = next((out / 'png').glob('DefineSprite_*')).name
            write_sheet(row['key'], out / 'png' / sprite, out / 'svg' / sprite, zoom, swf.frame_rate)
        index.setdefault(row['skill'], []).append({
            'sheet': f"{URL_DIR}/{row['key']}.json",
            'type': row['type'],
            'layer': row['layer'],
            'start': row['start'],
        })
        print(f"{row['key']}: {row['swf'].name}")

    (OUT_DIR / 'index.json').write_text(json.dumps(index))
    print(f'Wrote effects for {len(index)} jutsu to {OUT_DIR.relative_to(OUT_DIR.parents[2])}')


if __name__ == '__main__':
    main()

#!/usr/bin/env python3
"""Extract the original jutsu (skill) battle effects from the Pockie Ninja backup.

Usage: python3 tools/extract_effect_assets.py <path-to-game-pockieninja> [--hd] [--ultimates] [--missing]
--hd renders the vector effects at 2x (JPEXS zoom, no AI needed).
--ultimates only renders the ultimates and adds them to the existing index.
--missing keeps sheets already written (e.g. after adding outfits).

Needs JPEXS + Java, like tools/extract_ui_assets.py:
  JAVA       java binary   (default /opt/homebrew/opt/openjdk/bin/java)
  FFDEC_JAR  ffdec.jar     (default ~/.local/opt/jpexs/ffdec.jar)

For every skill-panel jutsu (clientskill Type 1) and the ultimate of every
outfit with art (1900 + outfit id, and <that>0 for outfits at +19 and up; their
cinematics sit in fighteffect/bigeffect) it reads the FightEffect_<id>* rows of
effectconfig, renders the 'MotionEffectSource' symbol of the matching
movieclip/fighteffect SWF and packs its frames into a Pixi spritesheet:

  public/game-assets/effects/<effect>-<page>.webp / .json   (anchor = SWF origin)
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
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

from amf3 import load_compressed
from swf import black_backdrops, frames_showing, read_swf, symbol_classes

OUT_DIR = Path(__file__).resolve().parent.parent / 'public' / 'game-assets' / 'effects'
URL_DIR = '/game-assets/effects'
SOURCE = 'apache/source'
EFFECT_DIR = 'movieclip/fighteffect'
SYMBOL = 'MotionEffectSource'
PANEL_SKILL = '1'  # clientskill Type 1 = skill panel
HEADER_ROW = 1
MAX_SHEET_WIDTH = 4096
HD_ZOOM = 2
WEBP_QUALITY = 85  # lossy keeps the cinematics' many pages small
WEBP_METHOD = 4
WORKERS = max(1, (os.cpu_count() or 2) // 2)
# Original stage-wide black shapes (Assassinate darkens the screen) would float as a box here;
# they are keyed out and replayed as a screen-wide shade instead.
MIN_BACKDROP_WIDTH = 400
FIGHT_EFFECT = re.compile(r'^FightEffect_(\d{4,5})(?:_\w+)?$')
BIG_EFFECT_DIR = 'bigeffect'
OUTFITS = Path(__file__).resolve().parent.parent / 'database' / 'data' / 'outfits.json'
ULTIMATE_BASE = 1900  # outfit 1 -> ultimate 1901, outfit 103 -> 2003


def latest_table(datatable: Path, name: str) -> dict:
    versions = sorted(datatable.glob(f'{name}.s*.tab'), key=lambda p: int(p.name.split('.s')[1].split('.')[0]))
    return load_compressed(versions[-1])


def start_of(play_time: str) -> int | str:
    """effectconfig PlayEffectTime: MotionStart, ByAttacking (on impact) or a delay in ms."""
    if play_time == 'ByAttacking':
        return 'hit'
    return int(play_time) if play_time.isdigit() else 0


def find_swf(effect_dir: Path, effect_id: str, source_id: str) -> Path | None:
    """FightEffect_18071 lives in fighteffect_1807_1.*; FightEffect_3826_M in fighteffect_3826_m.*;
    ultimates in bigeffect/."""
    digits = source_id.removeprefix('FightEffect_')
    stems = [f'fighteffect_{digits}', f'fighteffect_{digits[:4]}_{digits[4:]}', effect_id.lower()]
    for folder in (effect_dir, effect_dir / BIG_EFFECT_DIR):
        for stem in stems:
            matches = sorted(folder.glob(f'{stem}.*swf'))
            if matches:
                return matches[0]
    return None


def ultimate_ids(outfit_keys: list[str]) -> set[str]:
    """'0_1' -> '1901' and its upgraded cinematic '19010' (Kurosaki Ichigo +19 is avatar 1901)."""
    ids = set()
    for key in outfit_keys:
        ultimate = str(ULTIMATE_BASE + int(key.split('_')[1]))
        ids |= {ultimate, f'{ultimate}0'}
    return ids


def panel_ids(source: Path) -> set[str]:
    skills = latest_table(source / 'binary/datatable', 'clientskill')
    return {str(skills['FakeID'][i]) for i in range(HEADER_ROW, len(skills['FakeID'])) if skills['Type'][i] == PANEL_SKILL}


def effect_rows(source: Path, wanted: set[str]) -> list[dict]:
    datatable = source / 'binary/datatable'
    table = latest_table(datatable, 'effectconfig')
    rows = []
    for index in range(HEADER_ROW, len(table['EffectID'])):
        effect_id = str(table['EffectID'][index])
        match = FIGHT_EFFECT.match(effect_id)
        if not match or match.group(1) not in wanted:
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


def without_black(image):
    """Turn black into transparency (brightness becomes alpha), so art drawn over a black backdrop keeps its light."""
    from PIL import Image

    data = bytearray(image.tobytes())
    for i in range(0, len(data), 4):
        light = max(data[i:i + 3])
        if light:
            data[i:i + 3] = bytes(value * 255 // light for value in data[i:i + 3])
        data[i + 3] = data[i + 3] * light // 255
    return Image.frombytes('RGBA', image.size, bytes(data))


def write_sheet(key: str, frames_dir: Path, svg_dir: Path, zoom: float, fps: float, backdrop: list[bool]) -> list[str]:
    """Pack trimmed frames shelf-style onto as many WebP pages as they need; returns the pages' URLs.

    Every frame keeps the full-size anchor at the SWF origin. Big cinematics
    (ultimates) spread over several 4096 pages instead of being shrunk; only a
    single frame larger than a page is halved.
    """
    from PIL import Image

    paths = sorted(frames_dir.glob('*.png'), key=lambda p: int(p.stem))
    images = [Image.open(path).convert('RGBA') for path in paths]
    images = [without_black(image) if index < len(backdrop) and backdrop[index] else image for index, image in enumerate(images)]
    width, height = images[0].size
    ox, oy = origin_of(svg_dir / '1.svg', (width, height))
    while max(max((image.getbbox() or (0, 0, 1, 1))[2:]) for image in images) > MAX_SHEET_WIDTH:
        images = [image.resize((max(1, image.width // 2), max(1, image.height // 2))) for image in images]
        width, height, ox, oy, zoom = width / 2, height / 2, ox / 2, oy / 2, zoom / 2

    for old in OUT_DIR.glob(f'{key}.*'):  # the single-page layout of earlier runs
        old.unlink()
    urls, index = [], 0
    for number, placed in enumerate(pack(images)):
        page = f'{key}-{number}'
        sheet = Image.new('RGBA', (max(px + c.width for c, _, px, _ in placed), max(py + c.height for c, _, _, py in placed)))
        frame_data, names = {}, []
        for crop, box, px, py in placed:
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
            index += 1

        sheet.save(OUT_DIR / f'{page}.webp', 'WEBP', quality=WEBP_QUALITY, method=WEBP_METHOD)
        (OUT_DIR / f'{page}.json').write_text(json.dumps({
            'frames': frame_data,
            'animations': {'effect': names},
            'meta': {
                'image': f'{page}.webp', 'size': {'w': sheet.width, 'h': sheet.height}, 'scale': zoom, 'fps': fps,
                # Frames (of the whole effect) that darkened the original stage; the replay shades the screen.
                'backdrop': [i for i, dark in enumerate(backdrop) if dark],
            },
        }))
        urls.append(f'{URL_DIR}/{page}.json')
    return urls


def pack(images: list) -> list[list]:
    """Shelf-pack trimmed frames into pages of MAX_SHEET_WIDTH squared, in frame order."""
    pages, placed, x, y, shelf = [], [], 0, 0, 0
    for image in images:
        box = image.getbbox() or (0, 0, 1, 1)
        crop = image.crop(box)
        if x + crop.width > MAX_SHEET_WIDTH:
            x, y, shelf = 0, y + shelf, 0
        if y + crop.height > MAX_SHEET_WIDTH and placed:
            pages.append(placed)
            placed, x, y, shelf = [], 0, 0, 0
        placed.append((crop, box, x, y))
        x, shelf = x + crop.width, max(shelf, crop.height)
    pages.append(placed)
    return pages


def build_sheet(row: dict, java: str, ffdec: Path, zoom: int) -> dict:
    """Render one effect SWF into public/game-assets/effects/<key>-<page>.*; returns the row with its pages."""
    swf = read_swf(row['swf'])
    with tempfile.TemporaryDirectory() as tmp:
        out = Path(tmp)
        symbol = symbol_classes(swf)[SYMBOL]
        render(java, ffdec, row['swf'], symbol, zoom, out)
        sprite = next((out / 'png').glob('DefineSprite_*')).name
        backdrop = frames_showing(swf, symbol, black_backdrops(swf, MIN_BACKDROP_WIDTH))
        sheets = write_sheet(row['key'], out / 'png' / sprite, out / 'svg' / sprite, zoom, swf.frame_rate, backdrop)
    print(f"{row['key']}: {row['swf'].name} ({len(sheets)} pages)", flush=True)
    return {**row, 'sheets': sheets}


def main() -> None:
    flags = set(sys.argv[2:])
    if len(sys.argv) < 2 or not flags <= {'--hd', '--ultimates', '--missing'}:
        sys.exit(__doc__)
    zoom = HD_ZOOM if '--hd' in flags else 1
    java = os.environ.get('JAVA', '/opt/homebrew/opt/openjdk/bin/java')
    ffdec = Path(os.environ.get('FFDEC_JAR', '~/.local/opt/jpexs/ffdec.jar')).expanduser()
    if not ffdec.is_file() or not shutil.which(java):
        sys.exit(f'JPEXS or Java not found (FFDEC_JAR={ffdec}, JAVA={java}). See the module docstring.')

    source = Path(sys.argv[1]).expanduser() / SOURCE
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    ultimates = ultimate_ids([outfit['key'] for outfit in json.loads(OUTFITS.read_text())])
    only_ultimates = '--ultimates' in flags
    index_file = OUT_DIR / 'index.json'
    index: dict[str, list[dict]] = json.loads(index_file.read_text()) if only_ultimates and index_file.is_file() else {}
    for skill in ultimates & index.keys():
        del index[skill]
    rows = effect_rows(source, ultimates if only_ultimates else panel_ids(source) | ultimates)
    # JPEXS (a Java process per render) is the slow part: run a few at once.
    with ThreadPoolExecutor(max_workers=WORKERS) as pool:
        def build(row: dict) -> dict:
            pages = sorted(OUT_DIR.glob(f"{row['key']}-*.json"), key=lambda page: int(page.stem.rsplit('-', 1)[1]))
            if '--missing' in flags and pages:
                return {**row, 'sheets': [f'{URL_DIR}/{page.name}' for page in pages]}
            # Ultimates stay at the original size: at 2x their pages would run to gigabytes.
            return build_sheet(row, java, ffdec, 1 if row['skill'] in ultimates else zoom)

        for row in pool.map(build, rows):
            index.setdefault(row['skill'], []).append({
                'sheets': row['sheets'],
                'type': row['type'],
                'layer': row['layer'],
                'start': row['start'],
            })

    index_file.write_text(json.dumps(index))
    print(f'Wrote effects for {len(index)} jutsu to {OUT_DIR.relative_to(OUT_DIR.parents[2])}')


if __name__ == '__main__':
    main()

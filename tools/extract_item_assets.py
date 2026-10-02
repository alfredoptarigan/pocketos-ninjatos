#!/usr/bin/env python3
"""Extract the pharmacy items from the Pockie Ninja backup.

Usage: python3 tools/extract_item_assets.py <path-to-game-pockieninja> [--hd]
--hd AI-upscales the ~40px icons 4x into PNGs (see tools/upscale.py).

Writes item icons to public/game-assets/items/<code>.<gif|png> and the item data
to database/data/pharmacy_items.json, which ItemSeeder loads.
Output is gitignored: it is derived from copyrighted game files.
"""

from __future__ import annotations

import json
import shutil
import sys
from pathlib import Path

from amf3 import load_compressed
from upscale import available as upscaler_available
from upscale import upscale_all

ROOT = Path(__file__).resolve().parent.parent
ICON_OUT = ROOT / 'public' / 'game-assets' / 'items'
DATA_OUT = ROOT / 'database' / 'data' / 'pharmacy_items.json'
DATATABLE_DIR = 'apache/source/binary/datatable'
ICON_DIR = 'apache/source/bitmap/icon'
HEADER_ROW = 1  # row 0 holds Chinese column descriptions
HD_ICON_SCALE = 4  # icons are tiny; 4x keeps them crisp in detail panels on HiDPI screens

# English names for the original item name keys (source names are Chinese).
NAMES = {
    'Energy1': "Sakura's Miracle Pill", 'Energy2': "Shizune's Miracle Pill",
    'Energy3': "Tsunade's Miracle Pill", 'Energy4': 'Toad Secret Remedy',
    'Energy5': 'Leaf Secret Remedy',
    'HP1': 'Healing Powder', 'HP2': 'Healing Capsule',
    'HP3': 'Small Healing Potion', 'HP4': 'Medium Healing Potion',
    'HP5': 'Large Healing Potion', 'HP6': 'Small Healing Flask',
    'HP7': 'Medium Healing Flask', 'HP8': 'Large Healing Flask',
    'HP9': 'Stamina Essence', 'HP10': 'Stamina Source', 'HP11': 'Grand Stamina Source',
    'SP1': 'Chakra Powder', 'SP2': 'Chakra Capsule',
    'SP3': 'Small Chakra Potion', 'SP4': 'Medium Chakra Potion',
    'SP5': 'Large Chakra Potion', 'SP6': 'Small Chakra Flask',
    'SP7': 'Medium Chakra Flask', 'SP8': 'Large Chakra Flask',
    'SP9': 'Chakra Essence', 'SP10': 'Chakra Source', 'SP11': 'Grand Chakra Source',
    'HPSP1': 'Restoration Powder', 'HPSP2': 'Restoration Capsule',
    'HPSP3': 'Small Restoration Potion', 'HPSP4': 'Medium Restoration Potion',
    'HPSP5': 'Large Restoration Potion', 'HPSP6': 'Small Restoration Flask',
    'HPSP7': 'Medium Restoration Flask', 'HPSP8': 'Large Restoration Flask',
    'HPSP9': 'Energy Essence', 'HPSP10': 'Energy Source', 'HPSP11': 'Grand Energy Source',
}


def latest_table(datatable_dir: Path, name: str) -> Path:
    """Tables ship in several versions (name.s<version>.tab); take the newest."""
    versions = sorted(datatable_dir.glob(f'{name}.s*.tab'), key=lambda p: int(p.name.split('.s')[1].split('.')[0]))
    if not versions:
        raise FileNotFoundError(f'{datatable_dir}/{name}.s*.tab')
    return versions[-1]


def rows(table: dict) -> list[dict]:
    """Turn the column-oriented table into row dicts, skipping the header row."""
    count = len(table['ID'])
    return [{column: values[i] for column, values in table.items()} for i in range(HEADER_ROW, count)]


ICON_EXTENSIONS = ('gif', 'png')


def find_icon(icon_dir: Path, resource_id: str) -> Path:
    matches = sorted(
        path for ext in ICON_EXTENSIONS for path in icon_dir.rglob(f'{resource_id.lower()}.s*.{ext}')
    )
    if not matches:
        raise FileNotFoundError(f'icon {resource_id} not found under {icon_dir}')
    return matches[0]


def number(value) -> int:
    return int(value or 0)


def main() -> None:
    if len(sys.argv) not in (2, 3) or sys.argv[2:] not in ([], ['--hd']):
        sys.exit(__doc__)
    hd = '--hd' in sys.argv
    if hd and not upscaler_available():
        sys.exit('Real-ESRGAN not found; see tools/upscale.py.')
    backup = Path(sys.argv[1]).expanduser()
    table = load_compressed(latest_table(backup / DATATABLE_DIR, 'pharmacyitem'))

    ICON_OUT.mkdir(parents=True, exist_ok=True)
    DATA_OUT.parent.mkdir(parents=True, exist_ok=True)
    items = []
    originals: dict[str, Path] = {}
    for row in rows(table):
        key = row['Name'].removesuffix('_itemname')
        price = number(row['Price'])
        if key not in NAMES or price <= 0:
            continue  # portable containers are quest rewards, not shop stock
        code = row['ID']
        icon = find_icon(backup / ICON_DIR, row['ResourceID'])
        # HD icons are always PNG (GIF has no smooth alpha).
        icon_name = f'{code}.png' if hd else f'{code}{icon.suffix}'
        if hd:
            originals[icon_name] = icon
        else:
            shutil.copyfile(icon, ICON_OUT / icon_name)
        items.append({
            'code': code,
            'name': NAMES[key],
            'icon': f'/game-assets/items/{icon_name}',
            'price': price,
            'restore_hp': number(row['HP']),
            'restore_chakra': number(row['MP']),
            'restore_energy': number(row['SP']),
            'max_stack': number(row['ItemMaxFoldNum']) or 1,
        })

    if originals:
        from PIL import Image

        images = {name: Image.open(path).convert('RGBA') for name, path in originals.items()}
        for name, image in upscale_all(images, HD_ICON_SCALE).items():
            image.save(ICON_OUT / name)

    DATA_OUT.write_text(json.dumps(items, indent=2, ensure_ascii=False))
    print(f'Wrote {len(items)} pharmacy items to {DATA_OUT.relative_to(ROOT)}')


if __name__ == '__main__':
    main()

#!/usr/bin/env python3
"""Extract the pharmacy (Apotek) items from the Pockie Ninja backup.

Usage: python3 tools/extract_item_assets.py <path-to-game-pockieninja>

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

ROOT = Path(__file__).resolve().parent.parent
ICON_OUT = ROOT / 'public' / 'game-assets' / 'items'
DATA_OUT = ROOT / 'database' / 'data' / 'pharmacy_items.json'
DATATABLE_DIR = 'apache/source/binary/datatable'
ICON_DIR = 'apache/source/bitmap/icon'
HEADER_ROW = 1  # row 0 holds Chinese column descriptions

# Indonesian names for the original item name keys (source names are Chinese).
NAMES = {
    'Energy1': 'Obat Mujarab Sakura', 'Energy2': 'Obat Mujarab Shizune',
    'Energy3': 'Obat Mujarab Tsunade', 'Energy4': 'Obat Rahasia Katak',
    'Energy5': 'Obat Rahasia Konoha',
    'HP1': 'Bubuk Penyembuh', 'HP2': 'Kapsul Penyembuh',
    'HP3': 'Ramuan Penyembuh Kecil', 'HP4': 'Ramuan Penyembuh Sedang',
    'HP5': 'Ramuan Penyembuh Besar', 'HP6': 'Botol Penyembuh Kecil',
    'HP7': 'Botol Penyembuh Sedang', 'HP8': 'Botol Penyembuh Besar',
    'HP9': 'Sari Stamina', 'HP10': 'Sumber Stamina', 'HP11': 'Sumber Stamina Agung',
    'SP1': 'Bubuk Cakra', 'SP2': 'Kapsul Cakra',
    'SP3': 'Ramuan Cakra Kecil', 'SP4': 'Ramuan Cakra Sedang',
    'SP5': 'Ramuan Cakra Besar', 'SP6': 'Botol Cakra Kecil',
    'SP7': 'Botol Cakra Sedang', 'SP8': 'Botol Cakra Besar',
    'SP9': 'Sari Cakra', 'SP10': 'Sumber Cakra', 'SP11': 'Sumber Cakra Agung',
    'HPSP1': 'Bubuk Pemulih', 'HPSP2': 'Kapsul Pemulih',
    'HPSP3': 'Ramuan Pemulih Kecil', 'HPSP4': 'Ramuan Pemulih Sedang',
    'HPSP5': 'Ramuan Pemulih Besar', 'HPSP6': 'Botol Pemulih Kecil',
    'HPSP7': 'Botol Pemulih Sedang', 'HPSP8': 'Botol Pemulih Besar',
    'HPSP9': 'Sari Energi', 'HPSP10': 'Sumber Energi', 'HPSP11': 'Sumber Energi Agung',
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
    if len(sys.argv) != 2:
        sys.exit(__doc__)
    backup = Path(sys.argv[1]).expanduser()
    table = load_compressed(latest_table(backup / DATATABLE_DIR, 'pharmacyitem'))

    ICON_OUT.mkdir(parents=True, exist_ok=True)
    DATA_OUT.parent.mkdir(parents=True, exist_ok=True)
    items = []
    for row in rows(table):
        key = row['Name'].removesuffix('_itemname')
        price = number(row['Price'])
        if key not in NAMES or price <= 0:
            continue  # portable containers are quest rewards, not shop stock
        code = row['ID']
        icon = find_icon(backup / ICON_DIR, row['ResourceID'])
        icon_name = f'{code}{icon.suffix}'
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

    DATA_OUT.write_text(json.dumps(items, indent=2, ensure_ascii=False))
    print(f'{len(items)} item apotek ditulis ke {DATA_OUT.relative_to(ROOT)}')


if __name__ == '__main__':
    main()

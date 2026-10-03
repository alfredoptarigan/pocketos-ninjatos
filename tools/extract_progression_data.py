#!/usr/bin/env python3
"""Extract the character progression data from the Pockie Ninja backup.

Usage: python3 tools/extract_progression_data.py <path-to-game-pockieninja>

Writes database/data/avatar_collection.json: the Strength/Agility/Stamina an
outfit gives once recorded in the avatar collection (avatarcollect, keyed by
outfit id). Output is gitignored like the other extracted data.
"""

from __future__ import annotations

import json
import sys
from pathlib import Path

from amf3 import load_compressed
from extract_item_assets import latest_table, number

SOURCE = 'apache/source/binary'
ROOT = Path(__file__).resolve().parent.parent
DATA_OUT = ROOT / 'database' / 'data'


def all_rows(table: dict) -> list[dict]:
    """Column-oriented table to row dicts, dropping a Chinese header row if there is one."""
    first = next(iter(table))
    found = [{column: values[i] for column, values in table.items()} for i in range(len(table[first]))]
    return [row for row in found if str(row[first]).lstrip('i').isdigit()]


def collection(rows: list[dict]) -> dict[str, dict]:
    """avatarcollect -> {outfit id: {strength, agility, stamina}}."""
    return {
        str(number(row['ClothID'])): {
            'strength': number(row['Strengh']),
            'agility': number(row['Agility']),
            'stamina': number(row['Stramina']),
        }
        for row in rows
    }


def write(name: str, data) -> None:
    path = DATA_OUT / name
    path.write_text(json.dumps(data, indent=1, ensure_ascii=False))
    print(f'{len(data)} -> {path.relative_to(ROOT)}')


def main() -> None:
    if len(sys.argv) != 2:
        sys.exit(__doc__)
    binary = Path(sys.argv[1]).expanduser() / SOURCE
    table = lambda name: all_rows(load_compressed(latest_table(binary / 'datatable', name)))  # noqa: E731

    DATA_OUT.mkdir(parents=True, exist_ok=True)
    write('avatar_collection.json', collection(table('avatarcollect')))


if __name__ == '__main__':
    main()

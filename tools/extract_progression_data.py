#!/usr/bin/env python3
"""Extract the character progression data from the Pockie Ninja backup.

Usage: python3 tools/extract_progression_data.py <path-to-game-pockieninja>

Writes to database/data/ (gitignored like the other extracted data):
- avatar_collection.json: the Strength/Agility/Stamina an outfit gives once
  recorded in the avatar collection (avatarcollect, keyed by outfit id);
- titles.json: every title (title) with an English name and the bonuses
  parsed from its tooltip (lg_title_contentself*); stats this rework does
  not have (speed, hit, armor break, block...) are dropped;
- achievements.json: every achievement (accomplishment) with its target,
  points and reward title code. Names and which ones are tracked live in
  config/game.php ('achievements'): most need systems not built yet.
"""

from __future__ import annotations

import json
import re
import sys
from pathlib import Path

from amf3 import load_compressed
from extract_item_assets import latest_table, number
from extract_outfit_assets import outfit_name

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


# Tooltip stat -> StatBonus field; "%" marks percent stats.
TITLE_STATS = {
    'Strength': 'strength',
    'Agility': 'agility',
    'Stamina': 'stamina',
    'Max HP': 'hp',
    'Defense': 'defense',
    'Attack%': 'attackPercent',
    'HP%': 'hpPercent',
}
# English names for titles the English build left in Indonesian.
TITLE_NAMES = {
    'Tidak ada gelar': 'No title',
    'Murid Ninja': 'Ninja Student',
    'Ninja Akademi': 'Academy Ninja',
    'Ninja Genin': 'Genin',
    'Ninja Chunin': 'Chunin',
    'Ninja Jounin': 'Jonin',
    'Ninja Genius': 'Genius Ninja',
    'Terhormat': 'Honored',
    'Demi Cinta!': 'For Love!',
    'Berusaha menang': 'Striving to Win',
    'Semangat, anak muda!': 'Fight On, Youngster!',
    'Domba Gemuk Legendaris': 'Legendary Fat Sheep',
}
# Bankai titles start with a glyph of the client font (U+F8DC) and an 'e'.
BANKAI_MARK = r'^\uf8dce'
COLLECTION_RANKS = {'Pengumpul': 'Gatherer', 'Penjaga': 'Keeper', 'Kolektor': 'Collector', 'Koleksi': 'Curator', 'Katalog Buku': 'Cataloguer'}
COLLECTION_COLORS = {'Orange': 'Orange', 'Biru': 'Blue', 'Abu-Abu': 'Grey'}


def title_name(name: str) -> str:
    """English title name: the TITLE_NAMES fixes, '<rank> <colour>' collection titles and Bankai titles."""
    name = re.sub(BANKAI_MARK, 'Bankai ', outfit_name(name))
    for rank, english_rank in COLLECTION_RANKS.items():
        for color, english_color in COLLECTION_COLORS.items():
            if name == f'{rank} {color}':
                return f'{english_color} {english_rank}'
    name = re.sub(r'\((\d+) hari\)', r' (\1 days)', name)
    return TITLE_NAMES.get(name, name)


def title_bonus(tooltip: str) -> dict[str, float]:
    """'..[line]Strength    +13<br>HP    +4%<br>[line]..' -> {'strength': 13, 'hpPercent': 4}."""
    parts = tooltip.split('[line]')
    stats = {}
    for line in re.sub(r'<(?!br>)[^>]+>', '', parts[1] if len(parts) > 1 else '').split('<br>'):
        found = re.fullmatch(r'\s*(.+?)\s*\+\s*([\d.]+)(%?)\s*', line)
        field = found and TITLE_STATS.get(found[1] + found[3])
        if field:
            value = float(found[2])
            stats[field] = int(value) if value.is_integer() else value
    return stats


def titles(rows: list[dict], language: dict) -> list[dict]:
    """Every title but 0 ("no title"): id, code (its Name key), English name, category, bonus."""
    return [
        {
            'id': number(row['ID']),
            'code': row['Name'],
            'name': title_name(language.get(f"lg_{row['Name']}", row['Name'])),
            'category': number(row['Level']),
            'bonus': title_bonus(language.get(f"lg_{row['contentself']}", '')),
        }
        for row in rows
        if number(row['ID']) > 0
    ]


def achievements(rows: list[dict]) -> list[dict]:
    """accomplishment -> id, type, target, points, title code (None when it gives none)."""
    return [
        {
            'id': number(row['ID']),
            'type': number(row['Type']),
            'target': number(row['TotalAmount']),
            'points': number(row['CurrentAccomplishmentAmount']),
            'title': None if row['Title'] in ('', 'nothing') else row['Title'],
        }
        for row in rows
    ]


def write(name: str, data) -> None:
    path = DATA_OUT / name
    path.write_text(json.dumps(data, indent=1, ensure_ascii=False))
    print(f'{len(data)} -> {path.relative_to(ROOT)}')


def main() -> None:
    if len(sys.argv) != 2:
        sys.exit(__doc__)
    binary = Path(sys.argv[1]).expanduser() / SOURCE
    table = lambda name: all_rows(load_compressed(latest_table(binary / 'datatable', name)))  # noqa: E731

    languages = sorted((binary / 'keyvaluetable').glob('language.s*.kv'), key=lambda p: int(p.name.split('.s')[1].split('.')[0]))
    language = load_compressed(languages[-1])

    DATA_OUT.mkdir(parents=True, exist_ok=True)
    write('avatar_collection.json', collection(table('avatarcollect')))
    write('titles.json', titles(table('title'), language))
    write('achievements.json', achievements(table('accomplishment')))


if __name__ == '__main__':
    main()

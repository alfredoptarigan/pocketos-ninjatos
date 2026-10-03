#!/usr/bin/env python3
"""Extract the basic equipment line from the Pockie Ninja backup.

Usage: python3 tools/extract_equipment_assets.py <path-to-game-pockieninja> [--hd]
--hd AI-upscales the icons 4x into PNGs (see tools/upscale.py).

Reads the equipitem table and writes database/data/equipment.json (EquipmentSeeder)
plus icons to public/game-assets/equipment/<code>.<gif|png>. Output is gitignored.

Only the plain tiers listed in NAMES are taken (set pieces, event gear and the
level-99 leftovers are skipped). Rings and amulets
have no fixed stats in the original (they were rolled on identification), so they
get simple level-based stats here: amulets max HP, rings critical chance.
"""

from __future__ import annotations

import json
import shutil
import sys
from pathlib import Path

from amf3 import load_compressed
from extract_item_assets import find_icon, latest_table, number, rows
from upscale import available as upscaler_available
from upscale import upscale_all

ROOT = Path(__file__).resolve().parent.parent
ICON_OUT = ROOT / 'public' / 'game-assets' / 'equipment'
DATA_OUT = ROOT / 'database' / 'data' / 'equipment.json'
DATATABLE_DIR = 'apache/source/binary/datatable'
ICON_DIR = 'apache/source/bitmap/icon'
HD_ICON_SCALE = 4

# Original EquipType -> our slot key.
SLOTS = {
    'Weapon': 'weapon', 'Hat': 'hat', 'Cloth': 'armor', 'Glove': 'gloves',
    'Girdle': 'belt', 'Shoes': 'shoes', 'Amulet': 'amulet', 'Ring': 'ring',
}

WEAPON_ICON = 'Icon_Weapon_'

# Our balance for slots without fixed stats in the data.
AMULET_HP_PER_LEVEL = 5
AMULET_HP_BASE = 20
RING_CRIT_PER_LEVELS = 20  # +1% critical chance per 20 item levels, plus 1

# The basic tiers, with English names (source names are Chinese; ids without one got new names).
NAMES = {
    'i250101': 'Dog-Beating Stick', 'i250102': 'Wolf Fang Club', 'i250103': 'Purifying Hammer',
    'i250104': 'Glutton Club', 'i250105': 'Mountain Shaker', 'i250106': 'Boxing Club',
    'i250107': 'Starlight Wand', 'i250108': 'Eight-Edged Hammer', 'i250109': 'Pumpkin Hammer',
    'i250110': 'Thorn Club', 'i250111': 'Thousand-Ton Hammer', 'i250112': 'Dragon Fang Hammer',
    'i250113': 'Sky Quake Halberd', 'i250114': 'Rampage Hammer', 'i250115': 'Jade Crystal Axe',
    'i250116': 'Gilded Mallet', 'i250117': 'Demon Sealing Hammer', 'i250118': 'Guardian Staff',
    'i260101': "Butcher's Blade", 'i260102': 'Bighead Spike', 'i260103': 'Bone Piercer',
    'i260104': 'Trendy Fork', 'i260105': 'Gale Sabre', 'i260106': 'Heartbreaker',
    'i260107': 'Bold Cutter', 'i260108': 'Flowing Cloud Sword', 'i260109': 'Sun Veil Dagger',
    'i260110': 'Chrysanthemum Sword', 'i260111': 'Jade Shadow Sword', 'i260112': 'Netherworld Cleaver',
    'i260113': 'Cloud Splitter', 'i260114': 'Blood Realm Blade', 'i260115': 'Fishbone Sword',
    'i260116': 'Goldback Sabre', 'i260117': 'Cloudwing Sword', 'i260118': 'Demon Eye Blade',
    'i270101': 'Tickle Gloves', 'i270102': 'Razor Claw', 'i270103': 'Greedy Wolf Claw',
    'i270104': 'Banana Gloves', 'i270105': 'Azure Fist Blade', 'i270106': 'Shadow Claw',
    'i270107': 'Raging Bear Claw', 'i270108': 'Crimson Lotus Claw', 'i270109': 'Silver Shark Claw',
    'i270110': 'Wind Caltrop Claw', 'i270111': 'Flying Wing Claw', 'i270112': 'Anchor Gloves',
    'i270113': 'Demon Rune Claw', 'i270114': 'Stinging Bird Claw', 'i270115': 'Crab Pincer Gloves',
    'i270116': 'Streamlight Claw', 'i270117': 'Phantom Claw', 'i270118': 'Soulbinder Claw',
    'i220101': 'Playful Cap', 'i220102': 'Iron Pot Helm', 'i220103': 'Light Leather Cap',
    'i220104': 'Stealth Mask', 'i220105': 'Fire Cloud Hat', 'i220106': 'Leopard Helm',
    'i220107': 'Frost War Helm', 'i220108': 'Twilight Helm', 'i220109': 'Dragon Helm',
    'i240101': 'Playful Tunic', 'i240102': 'Village Garb', 'i240103': 'Iron Plate Armor',
    'i240104': 'Youth Shirt', 'i240105': 'Light Leather Vest', 'i240106': 'Turtle Hermit Armor',
    'i240107': 'Stealth Suit', 'i240108': 'Cat War Robe', 'i240109': 'Fire Cloud Armor',
    'i240110': 'Windborne Robe', 'i240111': 'Leopard Light Armor', 'i240112': 'Vajra Armor',
    'i240113': 'Frost War Armor', 'i240114': 'Azure Spirit Armor', 'i240115': 'Twilight Armor',
    'i240116': 'Storm Armor', 'i240117': 'Dragon Armor', 'i240118': 'Celestial Armor',
    'i230101': 'Playful Shoes', 'i230102': 'Iron War Boots', 'i230103': 'Light Leather Boots',
    'i230104': 'Stealth Boots', 'i230105': 'Fire Cloud Boots', 'i230106': 'Leopard Boots',
    'i230107': 'Frost War Boots', 'i230108': 'Twilight Boots', 'i230109': 'Dragon Boots',
    'i210101': 'Blue Tourmaline', 'i210102': 'Violet Tourmaline', 'i210103': 'Jade Pendant',
    'i210104': 'Rose Knot', 'i210105': 'Lilac Knot', 'i210106': 'Heart of Majesty',
    'i210107': 'Frost Star Necklace', 'i210108': 'Twilight Necklace', 'i210109': 'Dragon Necklace',
    'i280101': 'Bamboo Bracers', 'i280102': 'Sport Bracers', 'i280103': 'Turtle Hermit Bracers',
    'i280104': 'Cat Bracers', 'i280105': 'Windborne Bracers', 'i280106': 'Vajra Bracers',
    'i280107': 'Frost War Bracers', 'i280108': 'Twilight Bracers', 'i280109': 'Dragon Bracers',
    'i310101': 'Bamboo Belt', 'i310102': 'Sport Sash', 'i310103': 'Turtle Hermit Belt',
    'i310104': 'Cat Belt', 'i310105': 'Windborne Sash', 'i310106': 'Vajra Belt',
    'i310107': 'Frost Steel Belt', 'i310108': 'Twilight Belt', 'i310109': 'Dragon Belt',
    'i300101': 'Topaz Ring', 'i300102': 'Blazing Ring', 'i300103': 'Abyssal Ring',
    'i300104': 'Tulip Ring', 'i300105': 'Harvest Ring', 'i300106': 'Mercy Ring',
    'i300107': 'Frost Star Ring', 'i300108': 'Twilight Ring', 'i300109': 'Dragon Ring',
}


def piece(row: dict, icon: str) -> dict:
    slot = SLOTS[row['EquipType']]
    level = number(row['UseLevel'])
    return {
        'code': row['ID'],
        'name': NAMES[row['ID']],
        'slot': slot,
        'level': level,
        'icon': icon,
        'price': number(row['Price']),
        'min_attack': number(row['AttackMin']),
        'max_attack': number(row['AttackMax']),
        'defense': number(row['Defense']),
        'max_hp': level * AMULET_HP_PER_LEVEL + AMULET_HP_BASE if slot == 'amulet' else 0,
        'crit': 1 + level // RING_CRIT_PER_LEVELS if slot == 'ring' else 0,
        # 'Icon_Weapon_Sharp20' -> 'sharp20', the weapon motions drawn in hand.
        'look': row['ResourceID'].removeprefix(WEAPON_ICON).lower() if slot == 'weapon' else None,
    }


def main() -> None:
    if len(sys.argv) not in (2, 3) or sys.argv[2:] not in ([], ['--hd']):
        sys.exit(__doc__)
    hd = '--hd' in sys.argv
    if hd and not upscaler_available():
        sys.exit('Real-ESRGAN not found; see tools/upscale.py.')
    backup = Path(sys.argv[1]).expanduser()
    table = load_compressed(latest_table(backup / DATATABLE_DIR, 'equipitem'))

    ICON_OUT.mkdir(parents=True, exist_ok=True)
    DATA_OUT.parent.mkdir(parents=True, exist_ok=True)
    pieces, originals = [], {}
    for row in rows(table):
        if row['ID'] not in NAMES:
            continue
        source = find_icon(backup / ICON_DIR, row['ResourceID'])
        # HD icons are always PNG (GIF has no smooth alpha).
        icon_name = f"{row['ID']}.png" if hd else f"{row['ID']}{source.suffix}"
        if hd:
            originals[icon_name] = source
        else:
            shutil.copyfile(source, ICON_OUT / icon_name)
        pieces.append(piece(row, f'/game-assets/equipment/{icon_name}'))

    if originals:
        from PIL import Image

        images = {name: Image.open(path).convert('RGBA') for name, path in originals.items()}
        for name, image in upscale_all(images, HD_ICON_SCALE).items():
            image.save(ICON_OUT / name)

    DATA_OUT.write_text(json.dumps(pieces, indent=2, ensure_ascii=False))
    print(f'Wrote {len(pieces)} equipment pieces to {DATA_OUT.relative_to(ROOT)}')


if __name__ == '__main__':
    main()

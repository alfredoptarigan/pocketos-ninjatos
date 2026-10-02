#!/usr/bin/env python3
"""Extract the world map and its hunting grounds (original "out city" scenes).

Usage: python3 tools/extract_world_assets.py <path-to-game-pockieninja> [--hd]
--hd renders the map at 2x (JPEXS) and upscales monster art 2x (Real-ESRGAN).
Needs JPEXS + Java (see tools/extract_ui_assets.py) and Pillow.

Writes:
  public/game-assets/world/map.png            the world map (ui/sceneui/worldmap.swf)
  public/game-assets/world/spots/<scene>.png  each area's clickable region, cut out of the map
  public/game-assets/world.json               {background, width, height, spots: {scene: {image, x, y}}}
  public/game-assets/fields/<scene>.jpg       hunting ground backdrops (scene/outcity)
  public/game-assets/monsters/<id>/           monster motions + face
  public/game-assets/fields/<scene>/<spot>.png  search spots (a bush or tree to search, a keyed cache)
  public/game-assets/items/<code>.png         area keys that open the caches
  database/data/fields.json                   areas with their monsters (FieldSeeder)
Output is gitignored: it is derived from copyrighted game files.

The areas and their monsters come from the world map tooltips in language.lg
(lg_Tip_WorldMap_<scene>_<n>: entry level, monsters, bosses); monster stats come
from normalnpc / taskbossnpc. The backup lacks the art of every field monster,
so each borrows the motions of an unnamed original monster (motion/mob/*/n10xxx):
human-looking foes from human/, beasts and spirits from inhuman/, bosses from
the boss folders. ART_OVERRIDES pins a different stand-in when one fits better.
"""

from __future__ import annotations

import io
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

from amf3 import load_compressed
from extract_effect_assets import origin_of
from extract_item_assets import find_icon
from extract_tower_assets import STATS, extract_art, latest_table, number
from motion import find_motions, motion_frames
from swf import (
    TAG_DEFINE_BUTTON2,
    all_bitmaps,
    button_up_records,
    first_jpeg,
    iter_tags,
    placed_bitmaps,
    read_swf,
    top_level_placements,
)
from upscale import available as upscaler_available

ROOT = Path(__file__).resolve().parent.parent
ASSETS = ROOT / 'public' / 'game-assets'
DATA_OUT = ROOT / 'database' / 'data' / 'fields.json'
SOURCE = 'apache/source'
MAP_SWF = 'movieclip/ui/sceneui/worldmap.s12755.swf'
FIELD_DIR = 'movieclip/scene/outcity'
MOB_DIR = 'movieclip/motion/mob'
MAP_WIDTH, MAP_HEIGHT = 768, 426
HD_ZOOM = 2
TIP = re.compile(r'^lg_Tip_WorldMap_(\d+)_\d$')
TIP_TEXT = re.compile(r'^\s*(\S+?)进入等级：\s*(\d+)\s*级场景怪物：(.*)场景BOSS：(.*)$')
# Each village owns three areas (21xx = 111, 22xx = 121, ...); 26xx and 27xx are shared.
OWNERS = {'21': '111', '22': '121', '23': '131', '24': '141', '25': '151'}
HUMAN_HINTS = ('匪', '贼', '寇', '武', '忍', '僧', '盗', '剑士', '徒', '女', '婆', '姥', '魔将', '才藏', '八太', '千与', '汐守')
ART_OVERRIDES: dict[str, str] = {}
# roleoutsearch: the first row per scene is the free search spot, the second the keyed cache.
SPOTS = ('search', 'cache')
SEARCH_RATES = {
    'monster': 'GostRate', 'money': 'MoneyRate', 'key': 'KeyFactoryRate',
    'boss': 'BossFactoryRate', 'baby': 'BabyFactoryRate', 'item': 'ItemFactoryRate',
}
CLIP_PREFIX = 'OutCity_'
FALLBACK_KEY_ICON = 'Icon_Debris46'  # monster code -> stand-in art id, e.g. {'n33017': 'n10064'}

FIELD_NAMES = {
    '熔炉山地': 'Furnace Highlands', '日暮荒原': 'Dusk Wasteland', '烈焰峡': 'Blaze Gorge',
    '绿意小径': 'Verdant Path', '流花河': 'Petal River', '碧波平原': 'Azure Wave Plain',
    '千峰砥': 'Thousand Peaks', '雷音天梯': 'Thunder Stairway', '神木岭': 'Sacred Tree Ridge',
    '薰风山道': 'Breeze Mountain Trail', '苍空谷': 'Blue Sky Valley', '飓风原': 'Hurricane Plain',
    '伏龙甬道': 'Hidden Dragon Tunnel', '巨岩洞穴': 'Boulder Cave', '丰饶祭坛': 'Harvest Altar',
    '十字路口': 'Crossroads', '晨风绿野': 'Morning Breeze Meadow', '旭阳村': 'Rising Sun Village',
    '辉光林地': 'Glowing Woods', '幽邃秘道': 'Shadowed Passage', '永夜荒城': 'Eternal Night Ruins',
    '断魂谷': 'Soulbreak Valley', '死亡沼泽': 'Death Swamp', '绝望之原': 'Plain of Despair',
}
MONSTER_NAMES = {
    '向日葵': 'Sunflower', '蜇人蜂': 'Stinger Bee', '寿司怪': 'Sushi Monster', '赤穗匪徒': 'Scarlet Bandit',
    '黑暗武士': 'Dark Warrior', '番薯妖': 'Yam Demon', '笑面猴': 'Grinning Monkey', '饭团怪': 'Rice Ball Monster',
    '河童': 'Kappa', '蛮牛武魁': 'Bull Warlord', '玉米妖': 'Corn Demon', '掘地鼠': 'Burrowing Rat',
    '披发女鬼': 'Long-haired Ghost', '火焰妖': 'Flame Spirit', '魔将直政': 'Demon General Naomasa',
    '水滴妖': 'Droplet Spirit', '雷霆武者': 'Thunder Warrior', '魔将天海': 'Demon General Tenkai',
    '霹雳妖': 'Thunderbolt Spirit', '魔将康政': 'Demon General Yasumasa', '龙卷妖': 'Tornado Spirit',
    '魔将忠胜': 'Demon General Tadakatsu', '地缚妖': 'Earthbound Spirit', '魔将宗矩': 'Demon General Munenori',
    '树妖': 'Tree Demon', '食人花妖': 'Man-eating Flower', '长羽贼寇': 'Long-feather Raider',
    '放浪武者': 'Wandering Samurai', '蝠妖灭影': 'Bat Fiend Shadowbane', '流浪狗': 'Stray Dog',
    '包子怪': 'Bun Monster', '鬼面恶徒': 'Demon-mask Thug', '古渚忍者': 'Konagisa Ninja',
    '古渚汐守': 'Konagisa Tide Guard', '恶德山僧': 'Corrupt Mountain Monk', '山猪匪徒': 'Boar Bandit',
    '魔笛妖女': 'Demon Flute Witch', '旭阳山匪': 'Rising Sun Brigand', '赤铠巨寇': 'Red Armor Brute',
    '食腐秃鹰': 'Carrion Vulture', '吸血蝙蝠': 'Vampire Bat', '幼鹰': 'Eaglet', '森间忍众': 'Morima Ninja',
    '森间千与': 'Morima Chiyo', '鬼婆婆': 'Ghost Granny', '绿皮蛙': 'Green Frog', '小恶魔': 'Imp',
    '假面大盗': 'Masked Thief', '魔将忠次': 'Demon General Tadatsugu', '虹川八太': 'Nijikawa Hachita',
    '噬尸鼠': 'Ghoul Rat', '狂暴魔犬': 'Rabid Hellhound', '黑鹰': 'Black Hawk', '骷髅剑士': 'Skeleton Swordsman',
    '怨憎魔': 'Wrath Demon', '魔眼花妖': 'Evil-eye Flower', '咆哮魔猿': 'Roaring Ape', '堕落僧': 'Fallen Monk',
    '山谷匪徒': 'Valley Bandit', '义贼才藏': 'Noble Thief Saizo', '沼泽毒蛙': 'Swamp Toad',
    '阴沉树怪': 'Gloom Treant', '毒沼河童': 'Bog Kappa', '溺死鬼': 'Drowned Ghost', '毒雾妖': 'Miasma Fiend',
    '嗜血魔葵': 'Bloodthirsty Sunflower', '荒漠秃鹫': 'Desert Vulture', '欺诈鬼姥': 'Trickster Hag',
    '蛮荒剑士': 'Savage Swordsman', '金芒妖刃': 'Golden Demon Blade',
}


def strip_tags(text: str) -> str:
    return re.sub(r'<[^>]+>', '', text).strip()


def areas(language: dict) -> list[dict]:
    """Hunting grounds in scene order, from the world map tooltips."""
    found = {}
    for key, text in language.items():
        match = TIP.match(key)
        if not match or '进入等级' not in text or match.group(1) in found:
            continue
        name, level, monsters, bosses = TIP_TEXT.match(strip_tags(text)).groups()
        found[match.group(1)] = {
            'name': name, 'level': int(level),
            'monsters': monsters.split('、'), 'bosses': bosses.split('、'),
        }
    return [{'scene': scene, **found[scene]} for scene in sorted(found)]


def npc_rows(datatable: Path, language: dict) -> dict[str, dict]:
    """Chinese name -> the base row (shortest id) of normalnpc / taskbossnpc."""
    rows = {}
    for table_name in ('normalnpc', 'taskbossnpc'):
        table = latest_table(datatable, table_name)
        for index in range(1, len(table['ID'])):
            name = language.get('lg_' + table['Name'][index], '')
            row = {column: values[index] for column, values in table.items()}
            if name and (name not in rows or len(row['ID']) < len(rows[name]['ID'])):
                rows[name] = row
    return rows


def bitmap_motions(folder: Path) -> bool:
    """Some original monsters are drawn as vectors; only bitmap motions can be packed."""
    try:
        motions = find_motions(folder, f'motion_{folder.name[1:]}_{{action}}*.swf')
        return all(motion_frames(path) for path in motions.values())
    except (FileNotFoundError, ValueError):
        return False


class StandIns:
    """Hands out unnamed original monster art, one per field monster, in a stable order."""

    def __init__(self, mob_dir: Path):
        def pool(*folders: str) -> list[str]:
            return sorted(p.name for folder in folders for p in (mob_dir / folder).glob('n10*') if bitmap_motions(p))

        self.pools = {'human': pool('human'), 'beast': pool('inhuman'), 'boss': pool('humanboss', 'inhumanboss')}
        self.used = {key: 0 for key in self.pools}

    def take(self, chinese: str, boss: bool) -> str:
        kind = 'boss' if boss else 'human' if any(hint in chinese for hint in HUMAN_HINTS) else 'beast'
        pool = self.pools[kind]
        art = pool[self.used[kind] % len(pool)]
        self.used[kind] += 1
        return art


def jpexs() -> tuple[str, Path]:
    java = os.environ.get('JAVA', '/opt/homebrew/opt/openjdk/bin/java')
    ffdec = Path(os.environ.get('FFDEC_JAR', '~/.local/opt/jpexs/ffdec.jar')).expanduser()
    if not ffdec.is_file() or not shutil.which(java):
        sys.exit(f'JPEXS or Java not found (FFDEC_JAR={ffdec}, JAVA={java}).')
    return java, ffdec


def run_jpexs(args: list[str]) -> None:
    java, ffdec = jpexs()
    subprocess.run([java, '-Djava.awt.headless=true', '-jar', str(ffdec), *args], check=True, capture_output=True)


def render_map(source: Path, zoom: int) -> None:
    with tempfile.TemporaryDirectory() as tmp:
        run_jpexs(['-zoom', str(zoom), '-format', 'frame:png', '-export', 'frame', tmp, str(source / MAP_SWF)])
        shutil.copyfile(Path(tmp) / '1.png', ASSETS / 'world' / 'map.png')


def map_spots(source: Path) -> dict[str, dict]:
    """Each scene button's region, cut out of the map like the village buildings."""
    from PIL import Image

    swf = read_swf(source / MAP_SWF)
    bitmaps = all_bitmaps(swf)
    out = ASSETS / 'world' / 'spots'
    out.mkdir(parents=True, exist_ok=True)
    spots = {}
    for placement in top_level_placements(swf):
        if not (placement.name or '').startswith('scene_'):
            continue
        parts = [(bitmaps[b], x, y) for b, x, y in placed_bitmaps(swf, placement.character_id, placement.x, placement.y) if b in bitmaps]
        left, top = min(x for _, x, _ in parts), min(y for _, _, y in parts)
        width = round(max(x + image.width for image, x, _ in parts) - left)
        height = round(max(y + image.height for image, _, y in parts) - top)
        cutout = Image.new('RGBA', (width, height))
        for image, x, y in parts:
            cutout.alpha_composite(image, (round(x - left), round(y - top)))
        scene = placement.name.removeprefix('scene_')
        cutout.save(out / f'{scene}.png')
        spots[scene] = {'image': f'/game-assets/world/spots/{scene}.png', 'x': round(left), 'y': round(top)}
    return spots


def field_background(source: Path, scene: str) -> str:
    from PIL import Image

    out = ASSETS / 'fields'
    out.mkdir(parents=True, exist_ok=True)
    swf = sorted((source / FIELD_DIR).glob(f'background_{scene}.*swf'))[-1]
    Image.open(io.BytesIO(first_jpeg(read_swf(swf)))).convert('RGB').save(out / f'{scene}.jpg', quality=90)
    return f'/game-assets/fields/{scene}.jpg'


def search_rows(datatable: Path) -> dict[str, list[dict]]:
    table = latest_table(datatable, 'roleoutsearch')
    rows: dict[str, list[dict]] = {}
    for index in range(1, len(table['ID'])):
        row = {column: values[index] for column, values in table.items()}
        rows.setdefault(row['SceneID'], []).append(row)
    return {scene: sorted(found, key=lambda r: r['ID']) for scene, found in rows.items()}


def spot_art(source: Path, scene: str, search_clip: str) -> dict[str, dict]:
    """Render the scene's two clickable clips; the search spot is named by roleoutsearch, the cache is the other."""
    from PIL import Image

    # Scene files ship in several versions (name.s<version>.swf); take the newest.
    element = max((source / FIELD_DIR).glob(f'element_{scene}.*swf'), key=lambda p: int(p.name.split('.s')[1].split('.')[0]))
    swf = read_swf(element)
    buttons = {int.from_bytes(tag[:2], 'little'): tag for code, tag in iter_tags(swf.body, swf.first_tag) if code == TAG_DEFINE_BUTTON2}
    clips = {}
    for placement in top_level_placements(swf):
        if (placement.name or '').startswith(CLIP_PREFIX) and placement.character_id in buttons:
            sprite, dx, dy = button_up_records(buttons[placement.character_id])[0]
            spot = 'search' if placement.name == search_clip else 'cache'
            clips[spot] = (sprite, (placement.x or 0) + dx, (placement.y or 0) + dy)

    out = ASSETS / 'fields' / scene
    out.mkdir(parents=True, exist_ok=True)
    art = {}
    with tempfile.TemporaryDirectory() as tmp:
        ids = ','.join(str(sprite) for sprite, _, _ in clips.values())
        for fmt in ('png', 'svg'):
            run_jpexs(['-selectid', ids, '-format', f'sprite:{fmt}', '-export', 'sprite', f'{tmp}/{fmt}', str(element)])
        for spot, (sprite, x, y) in clips.items():
            folder = next(Path(tmp, 'png').glob(f'DefineSprite_{sprite}*')).name
            image = Image.open(Path(tmp, 'png', folder, '1.png'))
            ox, oy = origin_of(Path(tmp, 'svg', folder, '1.svg'), image.size)
            image.save(out / f'{spot}.png')
            art[spot] = {'image': f'/game-assets/fields/{scene}/{spot}.png', 'x': round(x - ox), 'y': round(y - oy)}
    return art


def key_item(source: Path, datatable: Path, code: str, field_name: str) -> dict:
    """The area key that opens its cache (giftbagitem), copied into the item icons."""
    table = latest_table(datatable, 'giftbagitem')
    resource = table['ResourceID'][table['ID'].index(code)]
    try:
        icon = find_icon(source / 'bitmap/icon', resource)
    except FileNotFoundError:
        # A few key icons are missing from the backup; any area key looks the same.
        icon = find_icon(source / 'bitmap/icon', FALLBACK_KEY_ICON)
    out = ASSETS / 'items'
    out.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(icon, out / f'{code}{icon.suffix}')
    return {'code': code, 'name': f'{field_name} Key', 'icon': f'/game-assets/items/{code}{icon.suffix}'}


def searches(source: Path, datatable: Path, scene: str, rows: list[dict], field_name: str) -> tuple[list[dict], dict | None]:
    art = spot_art(source, scene, rows[0]['ClipName'])
    found, key = [], None
    for spot, row in zip(SPOTS, rows):
        if spot not in art:
            continue  # e.g. 2605 has no cache clip
        needs = row['RequireItem'].split(',')[0] if row['RequireItem'] not in ('', '-1') else None
        if needs:
            key = key_item(source, datatable, needs, field_name)
        found.append({
            'spot': spot,
            'key': needs,
            'cooldown': number(row['TimeSpace']),
            'exp': number(row['ExpBase']),
            'rates': {name: number(row[column]) for name, column in SEARCH_RATES.items()},
            'art': art[spot],
        })
    return found, key


def monster(row: dict, chinese: str, boss: bool, art_id: str, source: Path, scale: int) -> dict:
    code = row['ID']
    art = ART_OVERRIDES.get(code, art_id)
    return {
        'code': code,
        'name': MONSTER_NAMES[chinese],
        'is_boss': boss,
        **{field: number(row[column]) for field, column in STATS.items()},
        'exp': number(row['NpcExp']),
        'art': extract_art(source, f'MapUserFace_{art.upper()}', scale),
    }


def main() -> None:
    if len(sys.argv) not in (2, 3) or sys.argv[2:] not in ([], ['--hd']):
        sys.exit(__doc__)
    hd = '--hd' in sys.argv
    if hd and not upscaler_available():
        sys.exit('Real-ESRGAN not found; see tools/upscale.py.')
    source = Path(sys.argv[1]).expanduser() / SOURCE
    datatable = source / 'binary/datatable'
    language = load_compressed(source / 'binary/lg/language.lg')

    (ASSETS / 'world').mkdir(parents=True, exist_ok=True)
    render_map(source, HD_ZOOM if hd else 1)
    world = {'background': '/game-assets/world/map.png', 'width': MAP_WIDTH, 'height': MAP_HEIGHT, 'spots': map_spots(source)}
    (ASSETS / 'world.json').write_text(json.dumps(world))

    npcs = npc_rows(datatable, language)
    search_table = search_rows(datatable)
    stand_ins = StandIns(source / MOB_DIR)
    art_by_name: dict[str, str] = {}
    fields = []
    for area in areas(language):
        foes = [(name, False) for name in area['monsters']] + [(name, True) for name in area['bosses']]
        monsters = []
        for chinese, boss in foes:
            # The same monster keeps the same stand-in in every area.
            art_by_name.setdefault(chinese, stand_ins.take(chinese, boss))
            monsters.append(monster(npcs[chinese], chinese, boss, art_by_name[chinese], source, 2 if hd else 1))
        name = FIELD_NAMES[area['name']]
        spots, key = searches(source, datatable, area['scene'], search_table.get(area['scene'], []), name)
        fields.append({
            'scene': area['scene'],
            'name': name,
            'searches': spots,
            'key_item': key,
            'village': OWNERS.get(area['scene'][:2]),
            'level': area['level'],
            'background': field_background(source, area['scene']),
            'monsters': sorted(monsters, key=lambda m: m['level']),
        })

    DATA_OUT.parent.mkdir(parents=True, exist_ok=True)
    DATA_OUT.write_text(json.dumps(fields, indent=1, ensure_ascii=False))
    print(f'Wrote {len(fields)} hunting grounds ({len(art_by_name)} monsters) to {DATA_OUT.relative_to(ROOT)}')


if __name__ == '__main__':
    main()

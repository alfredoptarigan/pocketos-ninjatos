# Asset pipeline

How Pocketo Ninjatos gets its art, sound and game data out of the original
Pockie Ninja backup, where everything ends up, and the traps found on the way.
Written for humans and AI agents picking up the work.

> **Copyright.** All extracted art, music and data belong to the original game.
> They are generated locally and **gitignored** (`/public/game-assets`,
> `/database/data`). Never commit them. Only the extraction scripts are in git.

## 1. The source: the backup repo

- Location: `~/Privates/game-pockieninja` (a separate git repo: "Backup from Pockie
  Ninja's service", the Indonesian "Ninjakita" deployment).
- Everything the extractors use lives under `apache/source/` (the Flash client's
  resource tree). Other top-level folders (`app/`, `op/`, `backupdb/`, ...) are the
  old server (native `DmEnter.exe` + Java libs, no game logic source) and DB dumps
  (they contain real player accounts, never reuse them).

Important folders inside `apache/source/`:

| Folder                                                                         | Contents                                                                                                                                                                             |
| ------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `movieclip/scene/maincity/`                                                    | Village scenes: `background_<id>.swf` (1920x1080 JPEG), `element_<id>*.swf` (clickable building cut-outs), `buildname_<id>.swf` (label positions, do not match the elements, unused) |
| `movieclip/motion/people/people_<id>/`                                         | Player motions `motion_<sex>_<id>_<action>_role.swf`                                                                                                                                 |
| `movieclip/motion/mob/{human,humanboss,inhuman,inhumanboss,searchboss}/n<id>/` | Monster motions `motion_<id>_<action>.swf`                                                                                                                                           |
| `movieclip/ui/`                                                                | Client UI: `uilookandfeel*.swf` (AsWing window skin), `uiresource*.swf`, `sceneui/bottommenu*.swf` (bottom menu), `fighting*.swf` (battle HUD), `fightbg/*.jpg` (battle backdrops)   |
| `movieclip/scene/battle/`                                                      | Old 500x300 battle backdrop                                                                                                                                                          |
| `movieclip/fighteffect/`, `dazhao/`                                            | Skill/hit effects; ultimates in `fighteffect/bigeffect`; `dazhao/` holds demo fights of them                                                                                         |
| `bitmap/peoplecreate/`                                                         | Create-screen portraits `avatars_<sex>_<id>_clothing_create.swf`                                                                                                                     |
| `bitmap/userfaceavatar/{people,mob,mapmob}/`                                   | Face icons                                                                                                                                                                           |
| `bitmap/npcbackphoto/`                                                         | NPC and boss portraits `n<id>.s*.png`                                                                                                                                                |
| `bitmap/icon/**/`                                                              | Item icons `icon_<resource>.s*.gif                                                                                                                                                   | png` |
| `binary/datatable/*.tab`                                                       | Game data tables (see section 3)                                                                                                                                                     |
| `binary/lg/language.lg`                                                        | All display text (Chinese)                                                                                                                                                           |
| `fighttxt/*.fight`                                                             | 36 recorded battles (JSON)                                                                                                                                                           |
| `music/*.mp3`                                                                  | Background music                                                                                                                                                                     |

Versioned files: many names carry a version suffix, `name.s<version>.ext`
(e.g. `pharmacyitem.s36042.tab`). Always take the **highest** version.

## 2. File formats

| Format                  | How to read it                                                                                                               | Code                                          |
| ----------------------- | ---------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------- |
| `.swf`                  | `CWS` = zlib-compressed after an 8-byte header, `FWS` = raw. Tags parsed in Python.                                          | `tools/swf.py`                                |
| JPEG bitmaps            | `DefineBitsJPEG2` (plain) and `DefineBitsJPEG3` (JPEG + zlib alpha plane, merged with Pillow)                                | `swf.jpeg3_bitmaps`                           |
| Lossless bitmaps        | `DefineBitsLossless2`, ARGB **premultiplied** (un-premultiply!) or colour-mapped                                             | `swf.lossless2_bitmaps`                       |
| Motions                 | One sprite whose timeline swaps bitmap-filled shapes per frame                                                               | `tools/motion.py`                             |
| Buttons                 | `DefineButton2`; the up-state records give the cut-out                                                                       | `swf.button_up_records`, `swf.placed_bitmaps` |
| Vector art (skins, HUD) | Not parsed in Python: rendered to PNG with **JPEXS**                                                                         | `tools/extract_ui_assets.py`                  |
| `.tab` tables           | zlib + **AMF3**, column-oriented: `{column: [values...]}`; row 0 is often a Chinese header (not always: `tollgate` has none) | `tools/amf3.py`                               |
| `language.lg`           | zlib + AMF3 dictionary, keys like `lg_name_n900001`, `lg_SkillName_1828`, `lg_SkillDes_1828`, `lg_HP1_itemname`              | `amf3.load_compressed`                        |
| `.fight`                | Plain JSON battle logs                                                                                                       | see `docs/combat-research.md`                 |

## 3. Data tables that matter

Decode any table with:

```python
from pathlib import Path
from amf3 import load_compressed          # run from tools/
table = load_compressed(Path('.../binary/datatable/pharmacyitem.s36042.tab'))
```

| Table                                                                                                   | Used for                                                                                |
| ------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------- |
| `pharmacyitem`                                                                                          | Pharmacy items (price, HP/MP/SP restore, stack, icon resource)                          |
| `singlegatenpc`, `sgategetexp`, `stollgatebossinfo`                                                     | Training Tower: 170 opponents, exp per floor, boss floors                               |
| `rolebase`                                                                                              | Base stats and aptitudes per avatar id (our growth numbers in `config/game.php`)        |
| `buildnpc`                                                                                              | Building keeper NPC per village (`n11004` = Leaf Village pharmacy)                      |
| `tollgate` → `subtollgate` → `fightmonsterpoint` → `monstergroup`, `normalnpc`                          | Story stages (level 11+), not built yet                                                 |
| `clientskill`, `clientskillid`, `serverskillconfig`, `fightskill`, `fightingbuffconfig`, `effectconfig` | Skills: ids, PreID, MPCostMul in `config/skills.php`; mechanics from `lg_SkillDes_<id>` |
| `equipitem`, `equipsuit`, `equipconsolidate`, `task`, `compete*`, `petitem`, `card_*`, `home*`, `crop*` | Future features                                                                         |

Full combat findings: `docs/combat-research.md`.

## 4. Tooling

| Tool                                              | Where                                                                                    | Needed by                                |
| ------------------------------------------------- | ---------------------------------------------------------------------------------------- | ---------------------------------------- |
| Python 3.9+ (stdlib)                              | system `python3`                                                                         | all extractors                           |
| Pillow                                            | `python3 -m pip install --user pillow`                                                   | village, character, tower, UI extractors |
| Java (OpenJDK)                                    | `brew install openjdk` → `/opt/homebrew/opt/openjdk/bin/java`                            | UI extractor only                        |
| JPEXS Free Flash Decompiler 26.3.0                | zip from the GitHub release unpacked to `~/.local/opt/jpexs/ffdec.jar`                   | UI extractor only                        |
| Real-ESRGAN (ncnn-vulkan, 2022-04-24 macOS build) | zip from the xinntao/Real-ESRGAN v0.2.5.0 release unpacked to `~/.local/opt/realesrgan/` | `--hd` option only                       |

`extract_ui_assets.py` reads `JAVA` and `FFDEC_JAR` env vars to override those paths.
JPEXS can also be used by hand to explore a SWF:

```bash
java -Djava.awt.headless=true -jar ~/.local/opt/jpexs/ffdec.jar \
  -format sprite:png,shape:png,frame:png -export sprite,shape,frame,symbolClass OUT_DIR FILE.swf
# only some characters: add  -selectid 23,70,111
```

## 5. The extractors

All take the backup path and write into this repo. Run them from the repo root.

| Script                               | Reads                                                                                                     | Writes                                                                                                                                           |
| ------------------------------------ | --------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| `tools/extract_village_assets.py`    | `scene/maincity/background_*.swf`, `element_*.swf`                                                        | `public/game-assets/villages/<id>.jpg`, `villages/<id>/<building>.png`, `villages.json`                                                          |
| `tools/extract_character_assets.py`  | `bitmap/peoplecreate`, `userfaceavatar/people`, `motion/people`                                           | `public/game-assets/characters/<sex>_<id>/{portrait.png, face.png, motions.png, motions.json}`                                                   |
| `tools/extract_item_assets.py`       | `datatable/pharmacyitem`, `bitmap/icon`                                                                   | `public/game-assets/items/<code>.<gif                                                                                                            | png>`, `database/data/pharmacy_items.json` |
| `tools/extract_ui_assets.py` (JPEXS) | `ui/uilookandfeel`, `ui/sceneui/bottommenu`, `ui/fighting`, `npcbackphoto`, `music`                       | `public/game-assets/ui/*.png`, `ui/menu/*.png`, `ui/fight/*.png`, `npcs/pharmacy.png`, `music/*.mp3`                                             |
| `tools/extract_tower_assets.py`      | `singlegatenpc`, `sgategetexp`, `language.lg`, `motion/mob`, `npcbackphoto`, `scene/battle`, `ui/fightbg` | `database/data/tower.json`, `public/game-assets/monsters/n<id>/...`, `battle/background.jpg`, `battle/backgrounds/*.jpg`, `music/singlegate.mp3` |

`tools/extract_skill_assets.py [--hd]` copies the skill-panel icons (`clientskill` Type 1,
`bitmap/icon/skill/`) to `public/game-assets/skills/<skill id>.png` (`--hd`: 4x), and the
battle status icons picked from `bitmap/icon/buff` (`STATUS_ICONS`) to
`public/game-assets/statuses/<status>.png`. Skill rules live in `config/skills.php`, translated
from `lg_SkillDes_<id>` (Indonesian) and the wiki. Gotchas: `clientskill` Type 1 holds the 50
panel skills (10 passives `28xx`, 40 actives); the panel position is `ClipName`
(`ClipGrid_<School>_Ahead<tier>`, School `FireE..WindE` = elements, `FireS..WindS` = body, tools,
seal, illusion, healing); `clientskillid` lists 13 levels per jutsu (id `L*10000+id`) but only
level 1 has text; `upskillcfg` gives the passive levels (1, 11 … 91). Scroll skills (Amaterasu,
Izanagi) are not in this backup.

Tower bosses: `singlegatenpc.ResourceID` `AvatarUserFace_N9<sex><avatar id>` is an avatar in
costume (avatar id = costume level * 100 + outfit id, e.g. `N90277` Aaroniero +2 = `0_77`), so
`extract_tower_assets.py` writes that avatar's motions to `characters/<sex>_<id>/` (also for Bleach
villains that are not wearable outfits, ids 77-83). The last ten bosses (Akatsuki `N9001xx`,
Orochimaru, Sasori, Itachi) only have an `npcbackphoto` bust; `BOSS_OUTFITS` maps them to the
outfits we extract (Kakuzu `0_103`.. Sasuke `0_111`, run `extract_outfit_assets.py` first), so they
fight in those motions with that outfit's ultimate; without the outfit art they keep the bust. Gotcha: floors 151-170 rate dodge,
block and crit 10x (like dungeon npcs); the extractor divides them by 10. English names
(`lg_name_n9001xx`) fill the floors `NAMES` does not cover. Gotcha: a later patch reused two mob
ids in a second kind folder (`n32056` Di Roy is `human/` and a beast in `inhumanboss/`, also
`n31027`); `mob_folder` keeps the folder with the oldest files, the one matching the face.

`tools/extract_dungeon_assets.py [--hd]` (run after the outfit extractor) reads `tollgate` →
`subtollgate` → `fightmonsterpoint`/`monstergroup` → `normalnpc` and writes
`database/data/dungeons.json` (`DungeonSeeder`), stage pictures `public/game-assets/dungeons/<code>.jpg`
and the backdrop `battle/backgrounds/fightbg_3102.jpg`. Gotchas: `tollgate` has no header row but
`fightmonsterpoint` row 0 is data too; `Hard` 1 means _strong_ monsters and 2 normal; dungeon npcs
rate dodge/parry/crit ~10x the tower scale (divided by 10); their art is not in the backup, so they
borrow `motion/mob` stand-ins and avatar-named bosses (`name_avatar45`) use the outfit art.

`tools/extract_outfit_assets.py [--hd]` reads `avataritem` (base +0 outfits are the rows with
`AvatarLevel` 1: sex, `ItemColor` 0/1/2 = grey/blue/orange, and `Clothing`, the `people_<id>` art
they wear) and English names `lg_avatar<AvatarID>` from `keyvaluetable/language`, and writes
`database/data/outfits.json` (`OutfitSeeder`) plus `face.png` and `motions.*` under
`public/game-assets/characters/<sex>_<Clothing>/` (no portrait: only create-screen avatars have
one). 85 outfits have art. Gotcha: the Shippuden outfits are avatars `40xx` (Kakuzu = 4003) wearing
`people_103`-`114`; the labels `lg_avatar103`.. ("Ggio Vega +1") belong to +1 upgrade rows of
outfits 3-14, so keying by AvatarID hid Kakuzu, Hidan, Deidara, Pain, Kisame, Konan, Sage Naruto,
Hebi Sasuke, Suigetsu and Karin. Little Jun (`0_87`) is drawn in vectors: JPEXS renders its
`MotionSource` symbol at the HD zoom. Nel, Chi, Priest, Zombie Lady, Arale, Bomb Rukia and the
original Konan (68) have no motions; Kabuto, Guren, Kurenai and Lisa are not in the backup.
Konan is forced to orange (the data says grey).
Upgrades +1..+N are other `avataritem` rows (`AvatarID = level * 100 + base`, `UseLevel`
3 per step); the backup has no upgrade cost or stats, so those are ours (`outfits.upgrade`).

`tools/extract_weapon_assets.py [--hd]` (after the outfit and equipment extractors) writes the
weapon held in battle: `motion/weapon/people_<id>/<action>/motion_<key>_<look>_<action>_weapon.swf`
is an overlay drawn tick for tick over the body motion from the same origin, so it lands in
`weapons/<outfit key>/<look>/motions.*` with the same sheet format. The look comes from the
weapon's icon (`Icon_Weapon_Sharp20` = `sharp20`, `equipment.look`). Each outfit only has art for
its own class (`rolebase.Popsinger`: 1 blunt, 2 sharp, 4 gloves, 7 all, keyed by avatar id);
`extract_outfit_assets.py` stores that class as `weapon_class` (null = all) and the game
enforces it when equipping. There is no weapon art for idle (1) or dodge (2), and
`0_88`, `1_86`, `1_89` only ship vector stubs. `people_59` spells gloves `glove`.

`tools/extract_progression_data.py` (character progression, no art) writes
`database/data/avatar_collection.json` from `avatarcollect` (Strength/Agility/Stamina per
recorded outfit, keyed by outfit id; `OutfitSeeder` merges it into `outfits.collection`).
`avatarcollectleveladd` (tiers: Color 1/2/3 = orange/blue/grey) is copied by hand into
`config/game.php` (`collection.tiers`). Gotcha: `avatarcollect` has no header row.
It also writes `titles.json` (`TitleSeeder`) from `title`: names `lg_<Name>` (Indonesian
ones translated in `TITLE_NAMES`) and bonuses parsed from the tooltip `lg_<contentself>`
(the `contentself` key does not match the title id). Stats we lack (speed, hit, armor
break, block, pierce, crit rating) are dropped. `achievements.json` (`AchievementSeeder`)
holds every `accomplishment` (target `TotalAmount`, points `CurrentAccomplishmentAmount`,
reward title code); only the ids in `config('game.achievements.tracked')` are seeded, with
English names from that config. Gotcha: `accomplishment` row 0 is data, not a header.

`tools/extract_equipment_assets.py [--hd]` reads `equipitem` and writes the 126 plain
equipment tiers (listed with English names in its `NAMES`; set pieces and event gear are
skipped) to `database/data/equipment.json` (`EquipmentSeeder`) and icons to
`public/game-assets/equipment/<code>.png`. Weapons, hats, armor, gloves, belts and shoes
use the table's `AttackMin`/`AttackMax`/`Defense`. Rings and amulets have no fixed stats in
the table (the original rolled them on identification, see `equipidentifypro`), so the
extractor gives amulets `level * 5 + 20` health and rings `1 + level / 20` % critical
chance. The client tables hold no drop lists (`singlegatenpc.DropLibID` is empty), so tower
drops are our own rule (`config('game.equipment')`).

`tools/extract_world_assets.py [--hd]` (JPEXS) builds the world map and its hunting grounds:

- The map is the first frame of `ui/sceneui/worldmap.s12755.swf` (768x426, rendered at zoom 2 for
  HD). Its buttons are named `scene_<id>` and show a region of the map. The extractor cuts each
  region out (`world/spots/<scene>.png`) so the page can alpha-hit-test it like village buildings.
- The areas are read from the world map tooltips in `language.lg`, not from a table:
  `lg_Tip_WorldMap_<scene>_<n>` holds the name, entry level, monsters and bosses (`n` is the
  viewer's village, `_0`..`_4`). There are 24 areas. Each village owns three (21xx = 111,
  22xx = 121, 23xx = 131, 24xx = 141, 25xx = 151; levels 1/11/21), and 26xx/27xx are shared
  (levels 16-65). 28xx and 181 are "not open yet" in the original too.
- Monster stats are the base rows (shortest id) of `normalnpc`/`taskbossnpc`, matched by Chinese
  name. Their levels match the original client (Sunflower 2, Stinger Bee 4, ...).
- Backdrops are `scene/outcity/background_<scene>.swf` (1920x1080 JPEG).
- Search spots come from `roleoutsearch`, with two rows per scene. The first row is the free
  search spot. Its `ClipName` (`OutCity_1110` or `OutCity_1109`) names the clickable tree or bush
  in `scene/outcity/element_<scene>.swf`. The second row is the cache, opened with the area key
  (`RequireItem` i1500xx, "<area> Secret Key" in `giftbagitem`, icon `bitmap/icon/debris`; a few
  icons are missing, so the extractor falls back to `Icon_Debris46`), and uses the other clip.
  The clip art is a DefineButton2 whose up-state sprite is rendered with JPEXS. The origin is
  found the same way as for the effects (SVG translate). Rates (`GostRate`, `MoneyRate`,
  `KeyFactoryRate`, `BossFactoryRate`, `BabyFactoryRate`, `ItemFactoryRate`) are per 10,000
  searches. `TimeSpace` is the cooldown, read here as seconds. `ExpBase` is paid on every search.
- The motion art of every field monster (`MapUserFace_N32001..N33043`) is missing from the
  backup. Each monster borrows an unnamed original monster (`motion/mob/{human,inhuman,*boss}/n10xxx`,
  bitmap motions only); pin a better match in `ART_OVERRIDES`.

`tools/extract_effect_assets.py [--hd] [--ultimates]` (JPEXS, 4 renders at once) renders the jutsu
battle effects, including every outfit's ultimate: id `1900 + outfit id` (`clientskill` Type 2) and
the stronger cinematic `<id>0` for outfits at +19 and up (avatar `1901` is Kurosaki Ichigo +19). Their
SWFs sit in `fighteffect/bigeffect/`; each draws its user (motion `996` is an empty stub) and the hit,
from the user's feet, for the usual ~400-unit spacing. They are rendered at zoom 1: the sheets are
halved to fit 4096 anyway. `--ultimates` re-renders only those and merges them into the index. For each
`FightEffect_<skill id>[_part]` row of `effectconfig` it finds the SWF in
`movieclip/fighteffect/` (`EffectSourceID` `FightEffect_18071` lives in `fighteffect_1807_1`,
`FightEffect_3826_M` in `fighteffect_3826_m`), renders its `MotionEffectSource` symbol and
packs the trimmed frames into `effects/<effect>.{png,json}`. The anchor is the SWF origin:
JPEXS puts it at the root `translate` of the frame SVG, and the PNG adds an even filter
margin around the SVG bounds. `effects/index.json` maps skill id to
`{sheet, type: attack|beaten, layer: before|under, start: ms|'hit'}` (`EffectType`,
`LayoutIndex`, `PlayEffectTime`). Sheets taller than 4096 px are halved (lower `meta.scale`).
Effects are laid out in original stage units for fighters ~400 units apart (a Fireball
explodes ~386 units in front of its caster), so the replay draws them at scale 1 and casts
jutsu with cast-time art from where the user stands. `BuffEffect_*` (status loops) and
effects whose SWF is missing from the backup (e.g. Earth Wall 3806) are not extracted.

Sound effects are not in the backup (the original client only shipped music). The battle uses
Kenney CC0 packs (impact, RPG audio, sci-fi, interface sounds) converted to AAC with
`afconvert -f m4af -d aac in.ogg out.m4a`. They are committed in `public/sfx/` with the licence.

Shared modules: `swf.py` (SWF parsing), `motion.py` (motion → Pixi spritesheet),
`amf3.py` (tables). Unit tests: `python3 -m unittest discover tools`.

### Full setup on a fresh clone

```bash
B=~/Privates/game-pockieninja
python3 tools/extract_village_assets.py $B
python3 tools/extract_character_assets.py $B
python3 tools/extract_item_assets.py $B
python3 tools/extract_ui_assets.py $B        # needs Java + JPEXS
python3 tools/extract_tower_assets.py $B
python3 tools/extract_skill_assets.py $B
python3 tools/extract_equipment_assets.py $B
python3 tools/extract_weapon_assets.py $B  # after outfits + equipment
python3 tools/extract_world_assets.py $B     # needs Java + JPEXS
python3 tools/extract_effect_assets.py $B    # needs Java + JPEXS
php artisan migrate
php artisan db:seed --class=ItemSeeder
php artisan db:seed --class=TowerSeeder
php artisan db:seed --class=EquipmentSeeder
php artisan db:seed --class=FieldSeeder
```

Re-running is safe: files are overwritten, seeders use `updateOrCreate`.

## 6. Output layout (`public/game-assets/`)

```
villages/<id>.jpg                     1920x1080 village background (ids 111..171)
villages/<id>/<building>.png          building cut-out, positioned by villages.json
villages.json                         {id: {background, buildings: {key: {image, x, y}}}}
characters/<sex>_<id>/portrait.png    create-screen art
characters/<sex>_<id>/face.png        HUD face
characters/<sex>_<id>/motions.{png,json}  Pixi spritesheet: idle, stance, run, attack, dodge, dead
items/<code>.<gif|png>                item icons (code = original id, e.g. i160006)
ui/frame.png, frame-ornament.png      window skin (9-slice frame + jewel cut out of it)
ui/button{,-hover,-pressed}.png       wooden buttons
ui/close{,-hover}.png                 window close X
ui/menu/<name>-{up,over,down}.png     bottom menu: bag, character, tools, forge, friends, missions, pet, gifts
ui/fight/*.png                        battle HUD: bar frames, hp/mp fills, VS, portrait frame, pet, lock slot, pills
npcs/pharmacy.png                     pharmacy keeper portrait (n11004)
monsters/n<id>/motions.{png,json}     animated opponents (+ face.png)
monsters/n<id>/portrait.png           tower bosses without battle art (Akatsuki, floors 161-170)
battle/background.jpg                 old 500x300 fallback backdrop
battle/backgrounds/fightbg_*.jpg      25 battle backdrops (one per 10 tower floors)
music/maincity1-4.mp3, blackcity.mp3, singlegate.mp3
skills/<skill id>.png                 jutsu icons
statuses/<status>.png                 battle status icons
equipment/<code>.png                  equipment icons
world/map.png, world/spots/<scene>.png  world map and its clickable regions (world.json)
fields/<scene>.jpg                    hunting ground backdrops
effects/<effect>.{png,json}           jutsu battle effects (Pixi spritesheet, animation 'effect')
effects/index.json                    skill id -> effects to play
```

`database/data/pharmacy_items.json` and `database/data/tower.json` feed `ItemSeeder`
and `TowerSeeder`.

## 6a. HD (AI-upscaled) character and opponent art

`extract_character_assets.py` and `extract_tower_assets.py` accept `--hd`:

```bash
python3 tools/extract_character_assets.py ~/Privates/game-pockieninja --hd   # ~5 min
python3 tools/extract_tower_assets.py ~/Privates/game-pockieninja --hd       # ~4 min
```

- `tools/upscale.py` runs Real-ESRGAN with the `realesrgan-x4plus-anime` model at 4x and
  scales down to 2x (smoother edges). Binary path: `REALESRGAN` env var, default
  `~/.local/opt/realesrgan/realesrgan-ncnn-vulkan`; needs numpy + Pillow
  (`python3 -m pip install --user numpy`).
- Battles draw fighters at their original size (`MOTION_SCALE` 1 in `game/battle/fighter.ts`),
  as small as in the original client; run the character and outfit extractors with `--hd`
  (about 30 min together) so they stay sharp when the stage is scaled up on large and retina
  screens. Tower bosses that are avatars (`0_77`..`0_83`) come from the tower extractor.
- **Alpha-safe:** colour and alpha are upscaled separately, and colours are first bled
  into the transparent margin. Without this the model turns the black transparent
  pixels into thick dark outlines.
- Motion frames are upscaled in one model run per character; `motions.json` gets
  `meta.scale: 2` so Pixi draws them at the original size, only sharper (no game code
  change). Atlas columns are chosen to keep sheets ≤ 4096 px wide.
- Faces, create-screen portraits and boss portraits are upscaled in place.
- Without `--hd` the extractors write the original 1x art; both work with the game.

### HD UI skin, battle HUD and item icons

- `extract_ui_assets.py` always renders vector art (window skin, bottom menu, battle HUD)
  at `UI_ZOOM = 2` with JPEXS `-zoom 2`. The frontend keeps original sizes: CSS
  `border-image` slices are in 2x pixels (`68 64 64 64` for the frame, `12` for buttons)
  while border widths stay 1x, and images get explicit sizes (`game-window.tsx`,
  `game-menu.tsx`, `battle-hud.tsx`). Change `UI_ZOOM` and those values together.
- `extract_ui_assets.py --hd` also upscales the bitmap NPC portrait 2x.
- `extract_item_assets.py --hd` upscales the ~24-40px item icons 4x into PNGs (the JSON
  then points at `.png`); re-run `php artisan db:seed --class=ItemSeeder` afterwards.

## 7. Id cheat sheet

- **Avatar keys** `"<sex>_<id>"` (0 male, 1 female): the 18 creatable avatars are listed
  with their stat growth in `config/game.php` (`avatars`).
- **Motion action ids** (players and monsters): `1` idle, `999` battle stance, `55` run,
  `52` attack, `2` dodge (back-flip), `100` knocked out. Some monsters lack `1`/`2`; the
  client falls back to `stance`.
- **All motions are drawn facing left.** The player stands on the right; opponents are
  mirrored (`scale.x = -1`). Boss portraits are not mirrored.
- **Villages** `111` Leaf, `121` Mist, `131` Cloud, `141` Wind, `151` Sound,
  `161` Waterfall, `171` Shadow (English names are ours; see `config/game.php`).
- **Building keys** (element clip names): `hall` (opens the Training Tower), `salve`
  (Pharmacy), `foundry`, `equip`, `reward`, `pet`, `arena`, `CardLink`, `meetingroom`;
  village 161 adds `RandPot`, 171 has `nation` and `middle`.
- **Bottom menu** (`bottommenu.s14661.swf` DefineButton2 ids): bag 17, tools 24, forge 31,
  friends 38, missions 45, pet 52, gifts 59, character 66.
- **Battle HUD** (`fighting.s53608.swf`): shapes `29` bar frame, `32` VS diamond,
  `33` VS text, `14` portrait frame, `72` empty pet, `111/115/117` pill button;
  sprites `23` HP fill (frame 100 = full), `21` chakra fill, `70` lock slot.
  Bar frame geometry: HP track at (38, 3) 370x12, chakra track at (26, 18) 306x9.
- **AsWing skin** (`uilookandfeel.s12755.swf`, found by class name via SymbolClass):
  `Frame_inactiveBG` (window), `Button_*Image`, `Frame_closeIcon_*Image`.

## 8. Gotchas found the hard way

- JPEG data may start with a bogus `FF D9 FF D8`; strip it (`swf.clean_jpeg`).
- Shape fill styles often start with a placeholder bitmap id `0xFFFF`; scan all fills.
- `DefineBitsLossless2` colours are premultiplied by alpha.
- `.tab` columns sometimes contain empty strings for 0; coerce with `int(v or 0)`.
- JPEXS names button frames `1_up.png`, `2_over.png`, `3_down.png`, and skips the
  `sprites/` subfolder when only one item type is exported.
- The village `buildname_*.swf` labels do not line up with the backgrounds; use the
  `element_*.swf` cut-outs.
- Pixi caches textures globally **by frame name**: spritesheet frame names carry the
  avatar/monster key (`0_12_attack_3`).
- In `composer run dev`, `app.css` is served by the Vite dev server, so relative
  `url(/game-assets/...)` breaks. Skin images are absolute `--ui-*` CSS variables
  declared in `resources/views/app.blade.php`.
- After adding routes run `php artisan wayfinder:generate --with-form` (plain
  `wayfinder:generate` drops the `.form()` helpers the pages use).
- The backup's `login.mp3` is a broken 209-byte stub; the scene-music config is empty,
  so village→track mapping is ours (`resources/js/game/music.ts`).

## 9. Adding a new asset type (recipe)

1. Find the files in `apache/source/` (check `SourceList.tab` for name → path).
2. Probe with JPEXS or `swf.py` (`read_swf`, `iter_tags`, `top_level_placements`,
   `sprite_timelines`) and look at the PNGs before writing code.
3. Add or extend a `tools/extract_*.py` script: write into `public/game-assets/...`
   or `database/data/...` (both gitignored), stable file names, English names.
4. Add a unit test in `tools/test_*.py` for new parsing logic.
5. Reference assets by URL (`/game-assets/...`) from the frontend; seed data via a
   seeder that fails with a clear "run the extractor" message when the JSON is missing.
6. Update this document.

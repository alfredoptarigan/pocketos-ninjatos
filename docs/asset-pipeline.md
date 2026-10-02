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
| `movieclip/fighteffect/`, `dazhao/`                                            | Skill/hit effects, ultimates (not used yet)                                                                                                                                          |
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

| Table                                                                                                   | Used for                                                                         |
| ------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------- |
| `pharmacyitem`                                                                                          | Pharmacy items (price, HP/MP/SP restore, stack, icon resource)                   |
| `singlegatenpc`, `sgategetexp`, `stollgatebossinfo`                                                     | Training Tower: 170 opponents, exp per floor, boss floors                        |
| `rolebase`                                                                                              | Base stats and aptitudes per avatar id (our growth numbers in `config/game.php`) |
| `buildnpc`                                                                                              | Building keeper NPC per village (`n11004` = Leaf Village pharmacy)               |
| `tollgate` → `subtollgate` → `fightmonsterpoint` → `monstergroup`, `normalnpc`                          | Story stages (level 11+), not built yet                                          |
| `clientskill`, `clientskillid`, `serverskillconfig`, `fightskill`, `fightingbuffconfig`, `effectconfig` | Skills and buffs, not built yet; mechanics are in `lg_SkillDes_<id>`             |
| `equipitem`, `equipsuit`, `equipconsolidate`, `task`, `compete*`, `petitem`, `card_*`, `home*`, `crop*` | Future features                                                                  |

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
php artisan migrate
php artisan db:seed --class=ItemSeeder
php artisan db:seed --class=TowerSeeder
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
monsters/n<id>/portrait.png           boss opponents (portrait only, no motions in the backup)
battle/background.jpg                 old 500x300 fallback backdrop
battle/backgrounds/fightbg_*.jpg      25 battle backdrops (one per 10 tower floors)
music/maincity1-4.mp3, blackcity.mp3, singlegate.mp3
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
  `~/.local/opt/realesrgan/realesrgan-ncnn-vulkan`; needs numpy + Pillow.
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

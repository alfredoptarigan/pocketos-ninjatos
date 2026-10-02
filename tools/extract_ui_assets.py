#!/usr/bin/env python3
"""Render the original game's window skin, menu buttons, battle HUD, NPC portraits and music.

Usage: python3 tools/extract_ui_assets.py <path-to-game-pockieninja> [--hd]

Vector art (skin, menu, battle HUD) is always rendered at UI_ZOOM (2x) for
sharp HiDPI screens; the frontend draws it at its original size.
--hd also AI-upscales the bitmap NPC portraits (see tools/upscale.py).

The skin is vector art, so it is rendered with JPEXS Free Flash Decompiler:
  JAVA       java binary   (default /opt/homebrew/opt/openjdk/bin/java)
  FFDEC_JAR  ffdec.jar     (default ~/.local/opt/jpexs/ffdec.jar)
Requires Pillow. Writes public/game-assets/ui/*.png, ui/menu/*.png, npcs/*.png
and music/*.mp3.
Output is gitignored: the source art is copyrighted and stays local.
"""

from __future__ import annotations

import os
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

from swf import read_swf, symbol_classes
from upscale import available as upscaler_available
from upscale import upscale_file

OUT_DIR = Path(__file__).resolve().parent.parent / 'public' / 'game-assets'
SKIN_SWF = 'apache/source/movieclip/ui/uilookandfeel.s12755.swf'
NPC_PORTRAITS = 'apache/source/bitmap/npcbackphoto'
MENU_SWF = 'apache/source/movieclip/ui/sceneui/bottommenu.s14661.swf'
MUSIC_DIR = 'apache/source/music'
FIGHT_SWF = 'apache/source/movieclip/ui/fighting.s53608.swf'

# Vector pieces are rendered at this zoom; CSS slices and image sizes in the
# frontend assume it (resources/css/app.css, game-window.tsx, game-menu.tsx).
UI_ZOOM = 2
HD_SCALE = 2

# Output file -> AsWing look-and-feel symbol used by the original client.
SKIN_SYMBOLS = {
    'frame-source': 'Frame_inactiveBG',  # the gold-trimmed navy game window
    'button': 'Button_defaultImage',
    'button-hover': 'Button_rolloverImage',
    'button-pressed': 'Button_pressedImage',
    'close': 'Frame_closeIcon_defaultImage',
    'close-hover': 'Frame_closeIcon_rolloverImage',
}

# The window frame's top edge carries a jewel ornament that must not be
# stretched: it is cut out and drawn separately, centred on the title.
ORNAMENT_BOX = (68, 0, 272, 34)  # left, top, right, bottom in 1x frame pixels
PLAIN_EDGE_COLUMN = 50  # a 1x column of undecorated top edge to patch the gap with

# Bottom menu buttons (DefineButton2 ids; the file has no class names).
# Identified by rendering them: each has up/over/down frames 1-3.
MENU_BUTTONS = {
    'bag': 17, 'tools': 24, 'forge': 31, 'friends': 38,
    'missions': 45, 'pet': 52, 'gifts': 59, 'character': 66,
}
BUTTON_STATES = {1: 'up', 2: 'over', 3: 'down'}

# Original background music; login.mp3 in the backup is a broken 209-byte stub.
MUSIC_TRACKS = ('maincity1', 'maincity2', 'maincity3', 'maincity4', 'blackcity')

# Battle HUD pieces from the original fight screen, identified by rendering them.
FIGHT_SHAPES = {
    'bar-frame-left': 29, 'bar-frame-right': 19, 'vs-diamond': 32, 'vs-text': 33,
    'portrait-frame': 14, 'pet-empty': 72,
    'pill': 111, 'pill-hover': 115, 'pill-pressed': 117,
}
# Sprite id -> frame: the health bar sprite is a 100-frame gauge; its last frame is full.
FIGHT_SPRITES = {'hp-fill': (23, 100), 'mp-fill': (21, 1), 'lock-slot': (70, 1)}

# Building keeper portraits (buildnpc table): Leaf Village pharmacy owner.
NPC_FILES = {'pharmacy': 'n11004'}


def render_symbols(java: str, ffdec: Path, swf_path: Path, ids: list[int], out: Path) -> None:
    subprocess.run(
        [
            java, '-Djava.awt.headless=true', '-jar', str(ffdec), '-zoom', str(UI_ZOOM),
            '-selectid', ','.join(map(str, ids)),
            '-format', 'sprite:png', '-export', 'sprite', str(out), str(swf_path),
        ],
        check=True,
        capture_output=True,
    )


def render_menu_buttons(java: str, ffdec: Path, swf_path: Path, menu_dir: Path) -> None:
    with tempfile.TemporaryDirectory() as tmp:
        out = Path(tmp)
        subprocess.run(
            [
                java, '-Djava.awt.headless=true', '-jar', str(ffdec), '-zoom', str(UI_ZOOM),
                '-selectid', ','.join(map(str, MENU_BUTTONS.values())),
                '-format', 'button:png', '-export', 'button', str(out), str(swf_path),
            ],
            check=True,
            capture_output=True,
        )
        for name, character_id in MENU_BUTTONS.items():
            for frame, state in BUTTON_STATES.items():
                # JPEXS names button frames <frame>_<state>.png, e.g. 2_over.png.
                shutil.copyfile(
                    out / f'DefineButton2_{character_id}' / f'{frame}_{state}.png',
                    menu_dir / f'{name}-{state}.png',
                )


def render_fight_hud(java: str, ffdec: Path, swf_path: Path, out_dir: Path) -> None:
    with tempfile.TemporaryDirectory() as tmp:
        out = Path(tmp)
        ids = [*FIGHT_SHAPES.values(), *(sprite for sprite, _ in FIGHT_SPRITES.values())]
        subprocess.run(
            [
                java, '-Djava.awt.headless=true', '-jar', str(ffdec), '-zoom', str(UI_ZOOM),
                '-selectid', ','.join(map(str, ids)),
                '-format', 'shape:png,sprite:png', '-export', 'shape,sprite', str(out), str(swf_path),
            ],
            check=True,
            capture_output=True,
        )
        for name, shape_id in FIGHT_SHAPES.items():
            shutil.copyfile(out / 'shapes' / f'{shape_id}.png', out_dir / f'{name}.png')
        for name, (sprite_id, frame) in FIGHT_SPRITES.items():
            source = next((out / 'sprites').glob(f'DefineSprite_{sprite_id}*')) / f'{frame}.png'
            shutil.copyfile(source, out_dir / f'{name}.png')


def copy_music(music_dir: Path, out_dir: Path) -> None:
    for track in MUSIC_TRACKS:
        source = sorted(music_dir.glob(f'{track}*.mp3'))[0]
        shutil.copyfile(source, out_dir / f'{track}.mp3')


def rendered_png(out: Path, character_id: int) -> Path:
    """JPEXS writes [sprites/]DefineSprite_<id>_<class>/1.png."""
    matches = list(out.glob(f'**/DefineSprite_{character_id}_*/1.png'))
    if not matches:
        raise FileNotFoundError(f'JPEXS did not render character {character_id}')
    return matches[0]


def split_frame(frame_png: Path, ui_dir: Path) -> None:
    from PIL import Image

    frame = Image.open(frame_png).convert('RGBA')
    box = tuple(value * UI_ZOOM for value in ORNAMENT_BOX)
    frame.crop(box).save(ui_dir / 'frame-ornament.png')

    left, top, right, bottom = box
    column = PLAIN_EDGE_COLUMN * UI_ZOOM
    plain = frame.crop((column, top, column + 1, bottom))
    body = frame.copy()
    for x in range(left, right):
        body.paste(plain, (x, top))
    body.save(ui_dir / 'frame.png')


def main() -> None:
    if len(sys.argv) not in (2, 3) or sys.argv[2:] not in ([], ['--hd']):
        sys.exit(__doc__)
    hd = '--hd' in sys.argv
    if hd and not upscaler_available():
        sys.exit('Real-ESRGAN not found; see tools/upscale.py.')
    backup = Path(sys.argv[1]).expanduser()
    java = os.environ.get('JAVA', '/opt/homebrew/opt/openjdk/bin/java')
    ffdec = Path(os.environ.get('FFDEC_JAR', '~/.local/opt/jpexs/ffdec.jar')).expanduser()
    if not ffdec.is_file() or not shutil.which(java):
        sys.exit(f'JPEXS or Java not found (FFDEC_JAR={ffdec}, JAVA={java}). See the module docstring.')

    skin = backup / SKIN_SWF
    classes = symbol_classes(read_swf(skin))
    ids = {name: classes[symbol] for name, symbol in SKIN_SYMBOLS.items()}

    ui_dir = OUT_DIR / 'ui'
    ui_dir.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory() as tmp:
        rendered = Path(tmp)
        render_symbols(java, ffdec, skin, list(ids.values()), rendered)
        for name, character_id in ids.items():
            png = rendered_png(rendered, character_id)
            if name == 'frame-source':
                split_frame(png, ui_dir)
            else:
                shutil.copyfile(png, ui_dir / f'{name}.png')

    npc_dir = OUT_DIR / 'npcs'
    npc_dir.mkdir(parents=True, exist_ok=True)
    for name, npc_id in NPC_FILES.items():
        portrait = sorted((backup / NPC_PORTRAITS).glob(f'{npc_id}.s*.png'))[0]
        shutil.copyfile(portrait, npc_dir / f'{name}.png')
        if hd:
            upscale_file(npc_dir / f'{name}.png', HD_SCALE)

    menu_dir = ui_dir / 'menu'
    menu_dir.mkdir(exist_ok=True)
    render_menu_buttons(java, ffdec, backup / MENU_SWF, menu_dir)

    fight_dir = ui_dir / 'fight'
    fight_dir.mkdir(exist_ok=True)
    render_fight_hud(java, ffdec, backup / FIGHT_SWF, fight_dir)

    music_dir = OUT_DIR / 'music'
    music_dir.mkdir(exist_ok=True)
    copy_music(backup / MUSIC_DIR, music_dir)

    print(
        f'Wrote {len(ids) + 1} UI images, {len(MENU_BUTTONS)} menu buttons, '
        f'{len(FIGHT_SHAPES) + len(FIGHT_SPRITES)} battle HUD pieces, '
        f'{len(NPC_FILES)} NPC portraits and {len(MUSIC_TRACKS)} music tracks'
    )


if __name__ == '__main__':
    main()

#!/usr/bin/env python3
"""Render the original game's window skin and NPC portraits for the web UI.

Usage: python3 tools/extract_ui_assets.py <path-to-game-pockieninja>

The skin is vector art, so it is rendered with JPEXS Free Flash Decompiler:
  JAVA       java binary   (default /opt/homebrew/opt/openjdk/bin/java)
  FFDEC_JAR  ffdec.jar     (default ~/.local/opt/jpexs/ffdec.jar)
Requires Pillow. Writes public/game-assets/ui/*.png and public/game-assets/npcs/*.png.
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

OUT_DIR = Path(__file__).resolve().parent.parent / 'public' / 'game-assets'
SKIN_SWF = 'apache/source/movieclip/ui/uilookandfeel.s12755.swf'
NPC_PORTRAITS = 'apache/source/bitmap/npcbackphoto'

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
ORNAMENT_BOX = (68, 0, 272, 34)  # left, top, right, bottom in frame pixels
PLAIN_EDGE_COLUMN = 50  # a column of undecorated top edge to patch the gap with

# Building keeper portraits (buildnpc table): Leaf Village pharmacy owner.
NPC_FILES = {'pharmacy': 'n11004'}


def render_symbols(java: str, ffdec: Path, swf_path: Path, ids: list[int], out: Path) -> None:
    subprocess.run(
        [
            java, '-Djava.awt.headless=true', '-jar', str(ffdec),
            '-selectid', ','.join(map(str, ids)),
            '-format', 'sprite:png', '-export', 'sprite', str(out), str(swf_path),
        ],
        check=True,
        capture_output=True,
    )


def rendered_png(out: Path, character_id: int) -> Path:
    """JPEXS writes [sprites/]DefineSprite_<id>_<class>/1.png."""
    matches = list(out.glob(f'**/DefineSprite_{character_id}_*/1.png'))
    if not matches:
        raise FileNotFoundError(f'JPEXS did not render character {character_id}')
    return matches[0]


def split_frame(frame_png: Path, ui_dir: Path) -> None:
    from PIL import Image

    frame = Image.open(frame_png).convert('RGBA')
    frame.crop(ORNAMENT_BOX).save(ui_dir / 'frame-ornament.png')

    left, top, right, bottom = ORNAMENT_BOX
    plain = frame.crop((PLAIN_EDGE_COLUMN, top, PLAIN_EDGE_COLUMN + 1, bottom))
    body = frame.copy()
    for x in range(left, right):
        body.paste(plain, (x, top))
    body.save(ui_dir / 'frame.png')


def main() -> None:
    if len(sys.argv) != 2:
        sys.exit(__doc__)
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

    print(f'Wrote {len(ids) + 1} UI images and {len(NPC_FILES)} NPC portraits')


if __name__ == '__main__':
    main()

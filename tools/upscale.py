"""AI upscaling of sprites with Real-ESRGAN (anime model), alpha-safe.

Real-ESRGAN treats the transparent area's colour as real pixels, so black
transparent pixels turn into dark outlines. Each image is therefore split:
  - colour: transparent pixels are first filled with nearby opaque colours
    ("bleeding"), then upscaled;
  - alpha: upscaled on its own as a grey image.
Both go through the model at 4x and are scaled down to the requested factor,
which also smooths the model's edges.

Needs numpy, Pillow and realesrgan-ncnn-vulkan:
  REALESRGAN  binary path (default ~/.local/opt/realesrgan/realesrgan-ncnn-vulkan)
"""

from __future__ import annotations

import os
import subprocess
import tempfile
from pathlib import Path

MODEL = 'realesrgan-x4plus-anime'
MODEL_SCALE = 4
BLEED_STEPS = 12  # pixels of colour pushed into the transparent margin
OPAQUE = 8        # alpha above this counts as visible colour


def binary() -> Path:
    return Path(os.environ.get('REALESRGAN', '~/.local/opt/realesrgan/realesrgan-ncnn-vulkan')).expanduser()


def available() -> bool:
    return binary().is_file()


def bleed(rgba):
    """Copy colours outward from visible pixels so edges have no dark fringe."""
    import numpy as np

    pixels = np.asarray(rgba, dtype=np.float32)
    colour, known = pixels[..., :3].copy(), pixels[..., 3] > OPAQUE
    for _ in range(BLEED_STEPS):
        total = np.zeros_like(colour)
        count = np.zeros(known.shape, dtype=np.float32)
        for dy, dx in ((-1, 0), (1, 0), (0, -1), (0, 1)):
            shifted_known = np.roll(known, (dy, dx), axis=(0, 1))
            total += np.roll(colour, (dy, dx), axis=(0, 1)) * shifted_known[..., None]
            count += shifted_known
        grow = ~known & (count > 0)
        colour[grow] = total[grow] / count[grow][:, None]
        known = known | grow
    return colour.clip(0, 255).astype(np.uint8)


def upscale_all(images: dict[str, object], factor: int) -> dict[str, object]:
    """Upscale many RGBA PIL images in one model run; returns the same keys."""
    import numpy as np
    from PIL import Image

    if factor not in (2, 4):
        raise ValueError('factor must be 2 or 4')

    with tempfile.TemporaryDirectory() as tmp:
        source, target = Path(tmp) / 'in', Path(tmp) / 'out'
        source.mkdir()
        target.mkdir()
        for index, image in enumerate(images.values()):
            rgba = image.convert('RGBA')
            Image.fromarray(bleed(rgba)).save(source / f'{index}_rgb.png')
            rgba.getchannel('A').convert('RGB').save(source / f'{index}_alpha.png')

        subprocess.run(
            [str(binary()), '-i', str(source), '-o', str(target), '-n', MODEL, '-s', str(MODEL_SCALE),
             '-m', str(binary().parent / 'models')],
            check=True,
            capture_output=True,
        )

        result = {}
        for index, (key, image) in enumerate(images.items()):
            size = (image.width * factor, image.height * factor)
            colour = Image.open(target / f'{index}_rgb.png').convert('RGB').resize(size, Image.LANCZOS)
            alpha = Image.open(target / f'{index}_alpha.png').convert('L').resize(size, Image.LANCZOS)
            # Keep fully transparent areas clean: the model can leave faint grey noise.
            alpha = Image.fromarray(np.where(np.asarray(alpha) < OPAQUE, 0, np.asarray(alpha)).astype(np.uint8))
            colour.putalpha(alpha)
            result[key] = colour
        return result


def upscale_file(path: Path, factor: int) -> None:
    """Upscale one image file in place."""
    from PIL import Image

    image = Image.open(path).convert('RGBA')
    upscale_all({path.name: image}, factor)[path.name].save(path)

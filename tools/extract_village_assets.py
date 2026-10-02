#!/usr/bin/env python3
"""Extract village backgrounds and building hotspots from the Pockie Ninja backup.

Usage: python3 tools/extract_village_assets.py <path-to-game-pockieninja>

Writes public/game-assets/villages/<id>.jpg and public/game-assets/villages.json.
Output is gitignored: the source art is copyrighted and stays local.
"""

import json
import struct
import sys
import zlib
from pathlib import Path

SCENE_DIR = 'apache/source/movieclip/scene/maincity'
OUT_DIR = Path(__file__).resolve().parent.parent / 'public' / 'game-assets'

TAG_END = 0
TAG_DEFINE_BITS_JPEG2 = 21
TAG_PLACE_OBJECT2 = 26
TWIPS_PER_PIXEL = 20

PLACE_HAS_CHARACTER = 0x02
PLACE_HAS_MATRIX = 0x04
PLACE_HAS_COLOR_TRANSFORM = 0x08
PLACE_HAS_RATIO = 0x10
PLACE_HAS_NAME = 0x20


def read_swf(path: Path) -> tuple[bytes, int]:
    """Return the uncompressed SWF body and the offset of its first tag."""
    data = path.read_bytes()
    signature = data[:3]
    if signature == b'CWS':
        body = zlib.decompress(data[8:])
    elif signature == b'FWS':
        body = data[8:]
    else:
        raise ValueError(f'{path}: unsupported SWF signature {signature!r}')
    rect_bits = body[0] >> 3
    rect_bytes = (5 + rect_bits * 4 + 7) // 8
    return body, rect_bytes + 4  # skip frame rate + frame count


def iter_tags(body: bytes, pos: int):
    while pos < len(body):
        (header,) = struct.unpack_from('<H', body, pos)
        pos += 2
        code, length = header >> 6, header & 0x3F
        if length == 0x3F:
            (length,) = struct.unpack_from('<I', body, pos)
            pos += 4
        yield code, body[pos:pos + length]
        pos += length
        if code == TAG_END:
            return


class BitReader:
    def __init__(self, data: bytes):
        self.data, self.pos = data, 0

    def unsigned(self, n: int) -> int:
        value = 0
        for _ in range(n):
            byte = self.data[self.pos >> 3]
            value = (value << 1) | ((byte >> (7 - (self.pos & 7))) & 1)
            self.pos += 1
        return value

    def signed(self, n: int) -> int:
        value = self.unsigned(n)
        return value - (1 << n) if n and value & (1 << (n - 1)) else value

    def consumed_bytes(self) -> int:
        return (self.pos + 7) // 8


def read_matrix(data: bytes) -> tuple[float, float, int]:
    """Return (translate_x, translate_y, byte length) of an SWF MATRIX record."""
    bits = BitReader(data)
    for _ in range(2):  # optional scale, then optional rotate/skew: skipped
        if bits.unsigned(1):
            n = bits.unsigned(5)
            bits.signed(n)
            bits.signed(n)
    n = bits.unsigned(5)
    x, y = bits.signed(n), bits.signed(n)
    return x / TWIPS_PER_PIXEL, y / TWIPS_PER_PIXEL, bits.consumed_bytes()


def skip_color_transform(data: bytes) -> int:
    bits = BitReader(data)
    has_add, has_mult = bits.unsigned(1), bits.unsigned(1)
    n = bits.unsigned(4)
    bits.unsigned(n * 4 * (has_add + has_mult))
    return bits.consumed_bytes()


def named_placements(body: bytes, pos: int) -> dict[str, tuple[int, int]]:
    """Top-level PlaceObject2 tags that carry both an instance name and a matrix."""
    placements = {}
    for code, tag in iter_tags(body, pos):
        if code != TAG_PLACE_OBJECT2:
            continue
        flags, offset = tag[0], 3  # flags + depth
        if not (flags & PLACE_HAS_NAME and flags & PLACE_HAS_MATRIX):
            continue
        if flags & PLACE_HAS_CHARACTER:
            offset += 2
        x, y, size = read_matrix(tag[offset:])
        offset += size
        if flags & PLACE_HAS_COLOR_TRANSFORM:
            offset += skip_color_transform(tag[offset:])
        if flags & PLACE_HAS_RATIO:
            offset += 2
        name = tag[offset:tag.index(b'\0', offset)].decode('latin1')
        placements[name] = (round(x), round(y))
    return placements


def extract_background(path: Path, out: Path) -> None:
    body, pos = read_swf(path)
    for code, tag in iter_tags(body, pos):
        if code == TAG_DEFINE_BITS_JPEG2:
            jpeg = tag[2:]  # skip character id
            # Flash tolerates a bogus EOI+SOI prefix before the real stream.
            if jpeg[:4] == b'\xff\xd9\xff\xd8':
                jpeg = jpeg[4:]
            out.write_bytes(jpeg)
            return
    raise ValueError(f'{path}: no JPEG background found')


def building_key(instance_name: str) -> str:
    return instance_name.removeprefix('clip_').removesuffix('_name')


def main() -> None:
    if len(sys.argv) != 2:
        sys.exit(__doc__)
    scene_dir = Path(sys.argv[1]).expanduser() / SCENE_DIR
    if not scene_dir.is_dir():
        sys.exit(f'Folder scene tidak ditemukan: {scene_dir}')

    (OUT_DIR / 'villages').mkdir(parents=True, exist_ok=True)
    villages = {}
    for background in sorted(scene_dir.glob('background_*.swf')):
        village_id = background.name.split('.')[0].removeprefix('background_')
        extract_background(background, OUT_DIR / 'villages' / f'{village_id}.jpg')

        labels = scene_dir / f'buildname_{village_id}.swf'
        placements = named_placements(*read_swf(labels)) if labels.exists() else {}
        villages[village_id] = {
            'background': f'/game-assets/villages/{village_id}.jpg',
            'buildings': {
                building_key(name): {'x': x, 'y': y}
                for name, (x, y) in placements.items()
            },
        }
        print(f'{village_id}: {len(placements)} bangunan')

    (OUT_DIR / 'villages.json').write_text(json.dumps(villages, indent=2))


if __name__ == '__main__':
    main()

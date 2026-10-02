"""Minimal SWF reader for the Pockie Ninja backup: tags, matrices, bitmaps, sprites.

Only the record types the asset extractors need are parsed.
"""

from __future__ import annotations

import io
import struct
import zlib
from dataclasses import dataclass
from pathlib import Path

TAG_END = 0
TAG_SHOW_FRAME = 1
TAG_DEFINE_SHAPE = 2
TAG_DEFINE_BITS_JPEG2 = 21
TAG_DEFINE_SHAPE2 = 22
TAG_PLACE_OBJECT2 = 26
TAG_DEFINE_SHAPE3 = 32
TAG_DEFINE_BITS_JPEG3 = 35
TAG_DEFINE_SPRITE = 39
SHAPE_TAGS = (TAG_DEFINE_SHAPE, TAG_DEFINE_SHAPE2, TAG_DEFINE_SHAPE3)

TWIPS_PER_PIXEL = 20
SOLID_FILL = 0x00
BITMAP_FILL_TYPES = (0x40, 0x41, 0x42, 0x43)
NO_BITMAP = 0xFFFF  # placeholder fill Flash authoring tools leave behind

PLACE_HAS_CHARACTER = 0x02
PLACE_HAS_MATRIX = 0x04
PLACE_HAS_COLOR_TRANSFORM = 0x08
PLACE_HAS_RATIO = 0x10
PLACE_HAS_NAME = 0x20

# Flash tolerates a bogus EOI+SOI prefix before the real JPEG stream.
BOGUS_JPEG_PREFIX = b'\xff\xd9\xff\xd8'


@dataclass(frozen=True)
class Swf:
    body: bytes
    first_tag: int
    frame_rate: float


@dataclass(frozen=True)
class Placement:
    depth: int
    character_id: int | None
    x: float | None
    y: float | None
    name: str | None


def read_swf(path: Path) -> Swf:
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
    (rate,) = struct.unpack_from('<H', body, rect_bytes)  # 8.8 fixed point
    return Swf(body, rect_bytes + 4, rate / 256)


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


def read_rect_bytes(data: bytes) -> int:
    bits = BitReader(data)
    n = bits.unsigned(5)
    bits.unsigned(n * 4)
    return bits.consumed_bytes()


def read_matrix(data: bytes) -> tuple[float, float, int]:
    """Return (translate_x, translate_y, byte length) of a MATRIX record, in twips."""
    bits = BitReader(data)
    for _ in range(2):  # optional scale, then optional rotate/skew: skipped
        if bits.unsigned(1):
            n = bits.unsigned(5)
            bits.signed(n)
            bits.signed(n)
    n = bits.unsigned(5)
    x, y = bits.signed(n), bits.signed(n)
    return x, y, bits.consumed_bytes()


def skip_color_transform(data: bytes) -> int:
    bits = BitReader(data)
    has_add, has_mult = bits.unsigned(1), bits.unsigned(1)
    n = bits.unsigned(4)
    bits.unsigned(n * 4 * (has_add + has_mult))
    return bits.consumed_bytes()


def read_place_object2(tag: bytes) -> Placement:
    flags = tag[0]
    (depth,) = struct.unpack_from('<H', tag, 1)
    offset, character_id, x, y, name = 3, None, None, None, None
    if flags & PLACE_HAS_CHARACTER:
        (character_id,) = struct.unpack_from('<H', tag, offset)
        offset += 2
    if flags & PLACE_HAS_MATRIX:
        tx, ty, size = read_matrix(tag[offset:])
        x, y = tx / TWIPS_PER_PIXEL, ty / TWIPS_PER_PIXEL
        offset += size
    if flags & PLACE_HAS_COLOR_TRANSFORM:
        offset += skip_color_transform(tag[offset:])
    if flags & PLACE_HAS_RATIO:
        offset += 2
    if flags & PLACE_HAS_NAME:
        name = tag[offset:tag.index(b'\0', offset)].decode('latin1')
    return Placement(depth, character_id, x, y, name)


def clean_jpeg(jpeg: bytes) -> bytes:
    return jpeg[4:] if jpeg[:4] == BOGUS_JPEG_PREFIX else jpeg


def first_jpeg(swf: Swf) -> bytes:
    for code, tag in iter_tags(swf.body, swf.first_tag):
        if code == TAG_DEFINE_BITS_JPEG2:
            return clean_jpeg(tag[2:])  # skip character id
    raise ValueError('no DefineBitsJPEG2 tag found')


def jpeg3_bitmaps(swf: Swf) -> dict:
    """Map character id -> RGBA PIL image for every DefineBitsJPEG3 tag."""
    from PIL import Image  # only the character extractor needs Pillow

    bitmaps = {}
    for code, tag in iter_tags(swf.body, swf.first_tag):
        if code != TAG_DEFINE_BITS_JPEG3:
            continue
        character_id, alpha_offset = struct.unpack_from('<HI', tag)
        jpeg = clean_jpeg(tag[6:6 + alpha_offset])
        image = Image.open(io.BytesIO(jpeg)).convert('RGB')
        alpha = zlib.decompress(tag[6 + alpha_offset:])
        image.putalpha(Image.frombytes('L', image.size, alpha))
        bitmaps[character_id] = image
    return bitmaps


def first_bitmap_fill(tag: bytes, offset: int, has_alpha: bool) -> tuple[int, float, float] | None:
    """Scan a FILLSTYLEARRAY for the first fill that references a real bitmap."""
    count = tag[offset]
    offset += 1
    for _ in range(count):
        fill_type = tag[offset]
        offset += 1
        if fill_type == SOLID_FILL:
            offset += 4 if has_alpha else 3
        elif fill_type in BITMAP_FILL_TYPES:
            (bitmap_id,) = struct.unpack_from('<H', tag, offset)
            tx, ty, size = read_matrix(tag[offset + 2:])
            offset += 2 + size
            if bitmap_id != NO_BITMAP:
                return bitmap_id, tx / TWIPS_PER_PIXEL, ty / TWIPS_PER_PIXEL
        else:
            return None  # gradients are never character frames; stop parsing
    return None


def shape_bitmap_fills(swf: Swf) -> dict[int, tuple[int, float, float]]:
    """Map shape id -> (bitmap id, x, y) for shapes filled with a bitmap."""
    fills = {}
    for code, tag in iter_tags(swf.body, swf.first_tag):
        if code not in SHAPE_TAGS:
            continue
        (shape_id,) = struct.unpack_from('<H', tag)
        offset = 2 + read_rect_bytes(tag[2:])
        fill = first_bitmap_fill(tag, offset, has_alpha=code == TAG_DEFINE_SHAPE3)
        if fill:
            fills[shape_id] = fill
    return fills


def top_level_placements(swf: Swf) -> list[Placement]:
    return [
        read_place_object2(tag)
        for code, tag in iter_tags(swf.body, swf.first_tag)
        if code == TAG_PLACE_OBJECT2
    ]


def sprite_timelines(swf: Swf) -> dict[int, list[dict[int, int]]]:
    """Map sprite id -> per-frame {depth: character id} of what is on stage."""
    timelines = {}
    for code, tag in iter_tags(swf.body, swf.first_tag):
        if code != TAG_DEFINE_SPRITE:
            continue
        (sprite_id,) = struct.unpack_from('<H', tag)
        frames, stage = [], {}
        for inner_code, inner in iter_tags(tag, 4):
            if inner_code == TAG_PLACE_OBJECT2:
                placement = read_place_object2(inner)
                if placement.character_id is not None:
                    stage = {**stage, placement.depth: placement.character_id}
            elif inner_code == TAG_SHOW_FRAME:
                frames.append(stage)
        timelines[sprite_id] = frames
    return timelines

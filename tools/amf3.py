"""Minimal AMF3 decoder for the Pockie Ninja data tables (.tab = zlib + AMF3).

Supports the types those files use: scalars, strings, arrays, objects,
byte arrays and vectors. Not a general-purpose implementation.
"""

from __future__ import annotations

import struct
import zlib
from pathlib import Path

UNDEFINED, NULL, FALSE, TRUE, INTEGER, DOUBLE, STRING = 0, 1, 2, 3, 4, 5, 6
ARRAY, OBJECT, BYTE_ARRAY = 9, 10, 12
VECTOR_INT, VECTOR_UINT, VECTOR_DOUBLE, VECTOR_OBJECT = 13, 14, 15, 16
VECTOR_FORMATS = {VECTOR_INT: '>i', VECTOR_UINT: '>I', VECTOR_DOUBLE: '>d'}
INT29_SIGN_BIT = 1 << 28


class Amf3Reader:
    def __init__(self, data: bytes):
        self.data, self.pos = data, 0
        self.strings: list[str] = []
        self.objects: list = []
        self.traits: list[tuple[bool, list[str]]] = []

    def byte(self) -> int:
        value = self.data[self.pos]
        self.pos += 1
        return value

    def u29(self) -> int:
        value = 0
        for index in range(4):
            byte = self.byte()
            if index == 3:
                return (value << 8) | byte
            value = (value << 7) | (byte & 0x7F)
            if not byte & 0x80:
                return value
        return value

    def take(self, n: int) -> bytes:
        chunk = self.data[self.pos:self.pos + n]
        self.pos += n
        return chunk

    def string(self) -> str:
        header = self.u29()
        if not header & 1:
            return self.strings[header >> 1]
        length = header >> 1
        if length == 0:
            return ''
        value = self.take(length).decode('utf-8', 'replace')
        self.strings.append(value)
        return value

    def value(self):
        marker = self.byte()
        if marker in (UNDEFINED, NULL):
            return None
        if marker in (FALSE, TRUE):
            return marker == TRUE
        if marker == INTEGER:
            value = self.u29()
            return value - (1 << 29) if value & INT29_SIGN_BIT else value
        if marker == DOUBLE:
            return struct.unpack('>d', self.take(8))[0]
        if marker == STRING:
            return self.string()
        if marker == ARRAY:
            return self.array()
        if marker == OBJECT:
            return self.object()
        if marker == BYTE_ARRAY:
            return self.take(self.u29() >> 1)
        if marker in VECTOR_FORMATS or marker == VECTOR_OBJECT:
            return self.vector(marker)
        raise ValueError(f'unsupported AMF3 marker {marker} at byte {self.pos - 1}')

    def array(self):
        header = self.u29()
        if not header & 1:
            return self.objects[header >> 1]
        dense_length = header >> 1
        slot = len(self.objects)
        self.objects.append(None)
        associative = {}
        while (key := self.string()) != '':
            associative[key] = self.value()
        dense = [self.value() for _ in range(dense_length)]
        result = {**associative, '__list__': dense} if associative else dense
        self.objects[slot] = result
        return result

    def object(self) -> dict:
        header = self.u29()
        if not header & 1:
            return self.objects[header >> 1]
        if not header & 2:
            dynamic, keys = self.traits[header >> 2]
        else:
            self.string()  # class name: unused
            dynamic = bool(header & 8)
            keys = [self.string() for _ in range(header >> 4)]
            self.traits.append((dynamic, keys))
        result: dict = {}
        self.objects.append(result)
        for key in keys:
            result[key] = self.value()
        if dynamic:
            while (key := self.string()) != '':
                result[key] = self.value()
        return result

    def vector(self, marker: int) -> list:
        header = self.u29()
        if not header & 1:
            return self.objects[header >> 1]
        length = header >> 1
        self.byte()  # fixed-length flag: unused
        result: list = []
        self.objects.append(result)
        if marker == VECTOR_OBJECT:
            self.string()  # element type name: unused
            result.extend(self.value() for _ in range(length))
        else:
            fmt = VECTOR_FORMATS[marker]
            size = struct.calcsize(fmt)
            result.extend(struct.unpack(fmt, self.take(size))[0] for _ in range(length))
        return result


def decode(data: bytes):
    return Amf3Reader(data).value()


def load_compressed(path: Path):
    """Decode a zlib-compressed AMF3 file such as a .tab table or language.lg."""
    return decode(zlib.decompress(path.read_bytes()))

"""Run: python3 -m unittest discover tools"""

import struct
import unittest

from swf import NO_BITMAP, first_bitmap_fill, read_matrix


def bits_to_bytes(bits: str) -> bytes:
    bits += '0' * (-len(bits) % 8)
    return bytes(int(bits[i:i + 8], 2) for i in range(0, len(bits), 8))


def translate_only_matrix(x_twips: int, y_twips: int, nbits: int = 12) -> bytes:
    def signed(value: int) -> str:
        return format(value & ((1 << nbits) - 1), f'0{nbits}b')

    # no scale, no rotate, nbits, translate x, translate y
    return bits_to_bytes('0' + '0' + format(nbits, '05b') + signed(x_twips) + signed(y_twips))


class ReadMatrixTest(unittest.TestCase):
    def test_reads_negative_translation_in_twips(self):
        x, y, size = read_matrix(translate_only_matrix(-460, 1260))

        self.assertEqual((x, y), (-460, 1260))
        self.assertEqual(size, 4)  # 7 + 24 bits rounded up


class FirstBitmapFillTest(unittest.TestCase):
    def test_skips_placeholder_and_solid_fills(self):
        matrix = translate_only_matrix(-460, -1260)
        fills = (
            bytes([3])  # fill style count
            + bytes([0x00, 1, 2, 3, 255])  # solid RGBA
            + bytes([0x43]) + struct.pack('<H', NO_BITMAP) + matrix
            + bytes([0x43]) + struct.pack('<H', 7) + matrix
        )

        self.assertEqual(first_bitmap_fill(fills, 0, has_alpha=True), (7, -23.0, -63.0))

    def test_returns_none_without_a_real_bitmap(self):
        fills = bytes([1, 0x00, 1, 2, 3])

        self.assertIsNone(first_bitmap_fill(fills, 0, has_alpha=False))


if __name__ == '__main__':
    unittest.main()

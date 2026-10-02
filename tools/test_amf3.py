"""Run: python3 -m unittest discover tools"""

import unittest

from amf3 import decode


class DecodeTest(unittest.TestCase):
    def test_decodes_negative_integer(self):
        # 0x04 integer, U29 0x1FFFFFFF is -1 as a 29-bit signed value
        self.assertEqual(decode(bytes([0x04, 0xFF, 0xFF, 0xFF, 0xFF])), -1)

    def test_decodes_column_table_with_string_references(self):
        # Dynamic anonymous object {"ID": ["a", "a"]}: the second "a" is a reference.
        data = bytes([
            0x0A, 0x0B, 0x01,              # object, dynamic, no sealed keys, class ""
            0x05, *b'ID',                  # key "ID"
            0x09, 0x05, 0x01,              # dense array of 2, no associative part
            0x06, 0x03, *b'a',             # "a"
            0x06, 0x02,                    # reference to string #1 ("a")
            0x01,                          # end of dynamic keys
        ])

        self.assertEqual(decode(data), {'ID': ['a', 'a']})

    def test_decodes_object_vector(self):
        data = bytes([0x10, 0x05, 0x00, 0x01, 0x04, 0x07, 0x04, 0x08])

        self.assertEqual(decode(data), [7, 8])


if __name__ == '__main__':
    unittest.main()

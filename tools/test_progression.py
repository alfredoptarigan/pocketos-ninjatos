import unittest

from extract_progression_data import all_rows, collection


class ProgressionTest(unittest.TestCase):
    def test_header_rows_are_dropped_but_data_rows_kept(self):
        table = {'ID': ['编号', '1', 'i290002'], 'Name': ['名字', 'a', 'b']}

        self.assertEqual(all_rows(table), [{'ID': '1', 'Name': 'a'}, {'ID': 'i290002', 'Name': 'b'}])

    def test_collection_is_keyed_by_outfit_id(self):
        rows = [{'ClothID': '47', 'Strengh': '2', 'Agility': '4', 'Stramina': ''}]

        self.assertEqual(collection(rows), {'47': {'strength': 2, 'agility': 4, 'stamina': 0}})


if __name__ == '__main__':
    unittest.main()

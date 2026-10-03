import unittest

from extract_progression_data import all_rows, collection, title_bonus, title_name, titles


class ProgressionTest(unittest.TestCase):
    def test_header_rows_are_dropped_but_data_rows_kept(self):
        table = {'ID': ['编号', '1', 'i290002'], 'Name': ['名字', 'a', 'b']}

        self.assertEqual(all_rows(table), [{'ID': '1', 'Name': 'a'}, {'ID': 'i290002', 'Name': 'b'}])

    def test_collection_is_keyed_by_outfit_id(self):
        rows = [{'ClothID': '47', 'Strengh': '2', 'Agility': '4', 'Stramina': ''}]

        self.assertEqual(collection(rows), {'47': {'strength': 2, 'agility': 4, 'stamina': 0}})

    def test_title_bonuses_keep_only_stats_we_have(self):
        tooltip = ("<font color='#5df9ff'>Klik</font><br>[line]Strength    +13<br>HP    +4%<br>"
                   "Attack    +4.1%<br>Max Attack+11<br>Armor Break    +52<br>[line]<font>Mencapai</font>")

        self.assertEqual(title_bonus(tooltip), {'strength': 13, 'hpPercent': 4, 'attackPercent': 4.1})
        self.assertEqual(title_bonus('Klik[line]Tidak ada efek tambahan<br>[line]x'), {})

    def test_title_names_are_english(self):
        self.assertEqual(title_name('Murid Ninja'), 'Ninja Student')
        self.assertEqual(title_name('Katalog Buku Abu-Abu'), 'Grey Cataloguer')
        self.assertEqual(title_name('Brave Aries(7 hari)'), 'Brave Aries (7 days)')
        self.assertEqual(title_name('Wake of Sharingan'), 'Wake of Sharingan')

    def test_titles_skip_no_title(self):
        rows = [{'ID': '0', 'Name': 'EffortTitle00', 'Level': '0', 'contentself': 'c0'},
                {'ID': '1', 'Name': 'EffortTitle01', 'Level': '1', 'contentself': 'c1'}]
        language = {'lg_EffortTitle01': 'Murid Ninja', 'lg_c1': 'x[line]Stamina    +13<br>[line]'}

        self.assertEqual(titles(rows, language), [
            {'id': 1, 'code': 'EffortTitle01', 'name': 'Ninja Student', 'category': 1, 'bonus': {'stamina': 13}},
        ])


if __name__ == '__main__':
    unittest.main()

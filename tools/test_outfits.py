import unittest

from extract_outfit_assets import outfits, weapon_classes

LANGUAGE = {
    'lg_avatar47': "<font color='#ff8a00'>Hatake Kakashi ＋0</font>",
    'lg_avatar68': "<font color='#8e8e8e'>Konan ＋0</font>",
    'lg_avatar88': "<font color='#00aeff'>Kostum Natal (Male) +0</font>",
    'lg_avatar147': "<font color='#ff8a00'>Hatake Kakashi ＋1</font>",
    'lg_avatar103': "<font color='#8e8e8e'>Ggio Vega ＋1</font>",
    'lg_avatar4003': 'Kakuzu+0',
}


def item(avatar_id, sex, color, level=1, clothing=None):
    return {'AvatarID': str(avatar_id), 'Sex': str(sex), 'ItemColor': str(color),
            'AvatarLevel': str(level), 'Clothing': str(clothing or avatar_id)}


class OutfitTest(unittest.TestCase):
    def test_base_outfits_get_english_names_and_rarities(self):
        classes = weapon_classes({'ID': ['avatar', '47', '68', '88', '4003'], 'Popsinger': ['class', '2', '4', '7', '4']})
        found = outfits([
            item(88, 0, 1), item(47, 0, 2), item(68, 1, 0), item(147, 0, 2, level=2, clothing=47), item(91, 0, 0),
            # Shippuden outfits: avatar 40xx draws art people_1xx, whose own label belongs to a +1 item.
            item(103, 0, 0, level=2, clothing=3), item(4003, 0, 2, clothing=103),
        ], LANGUAGE, classes)

        self.assertEqual(found, [
            {'key': '0_47', 'name': 'Hatake Kakashi', 'sex': 0, 'rarity': 'orange', 'weapon_class': 'sharp'},
            {'key': '1_68', 'name': 'Konan', 'sex': 1, 'rarity': 'orange', 'weapon_class': 'gloves'},
            {'key': '0_88', 'name': 'Christmas (Male)', 'sex': 0, 'rarity': 'blue', 'weapon_class': None},
            {'key': '0_103', 'name': 'Kakuzu', 'sex': 0, 'rarity': 'orange', 'weapon_class': 'gloves'},
        ])


if __name__ == '__main__':
    unittest.main()

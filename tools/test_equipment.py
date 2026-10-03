import unittest

from extract_equipment_assets import piece

ROW = {'ID': 'i260118', 'EquipType': 'Weapon', 'UseLevel': '86', 'Price': '125', 'AttackMin': '176',
       'AttackMax': '213', 'Defense': '0', 'ResourceID': 'Icon_Weapon_Sharp20'}


class EquipmentTest(unittest.TestCase):
    def test_weapons_carry_the_look_drawn_in_hand(self):
        self.assertEqual(piece(ROW, '')['look'], 'sharp20')
        self.assertIsNone(piece({**ROW, 'ID': 'i220101', 'EquipType': 'Hat'}, '')['look'])


if __name__ == '__main__':
    unittest.main()

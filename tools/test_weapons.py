import tempfile
import unittest
from pathlib import Path

from extract_weapon_assets import weapon_motions


class WeaponTest(unittest.TestCase):
    def test_finds_the_weapon_motions_of_an_outfit_and_look(self):
        with tempfile.TemporaryDirectory() as tmp:
            folder = Path(tmp)
            for action, name in {'999': 'motion_1_59_glove3_999_weapon.swf', '52': 'motion_1_59_glove3_52_weapon.swf'}.items():
                (folder / action).mkdir()
                (folder / action / name).touch()

            found = weapon_motions(folder, '1_59', 'gloves3')

            self.assertEqual(sorted(found), ['attack', 'stance'])
            self.assertEqual(weapon_motions(folder, '1_59', 'sharp3'), {})


if __name__ == '__main__':
    unittest.main()

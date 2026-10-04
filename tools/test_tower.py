import tempfile
import unittest
from pathlib import Path

from extract_tower_assets import STATS, boss_avatar, costume_level, mob_folder, stats


class TowerTest(unittest.TestCase):
    def test_avatar_bosses_map_to_their_outfit(self):
        self.assertEqual(boss_avatar('AvatarUserFace_N90277'), 77)    # Aaroniero +2
        self.assertEqual(boss_avatar('AvatarUserFace_N91229'), 29)    # Halibel +2
        self.assertIsNone(boss_avatar('AvatarUserFace_N900163'))      # Kakuzu: not an avatar
        self.assertIsNone(boss_avatar('MapUserFace_N32053'))
        self.assertIsNone(boss_avatar('TGateUserFace_N4025111'))
        self.assertEqual(costume_level('AvatarUserFace_N90277'), 2)
        self.assertEqual(costume_level('AvatarUserFace_N91229'), 2)


    def test_late_floors_rate_dodge_block_and_crit_ten_times_higher(self):
        npcs = {column: ['', '7'] + ['0'] * 149 + ['256'] for column in STATS.values()}

        self.assertEqual(stats(npcs, 1)['dodge'], 7)
        self.assertEqual(stats(npcs, 151)['dodge'], 25)
        self.assertEqual(stats(npcs, 151)['crit'], 25)
        self.assertEqual(stats(npcs, 151)['max_hp'], 256)

    def test_a_reused_mob_id_keeps_its_original_art(self):
        with tempfile.TemporaryDirectory() as tmp:
            mob = Path(tmp)
            for kind, files in {'human': ['motion_32056_7.swf', 'motion_32056_1.s117.swf'],
                                'inhumanboss': ['motion_32056_17.swf', 'motion_32056_52.s27942.swf']}.items():
                (mob / kind / 'n32056').mkdir(parents=True)
                for name in files:
                    (mob / kind / 'n32056' / name).touch()

            self.assertEqual(mob_folder(mob, 'n32056'), mob / 'human' / 'n32056')


if __name__ == '__main__':
    unittest.main()

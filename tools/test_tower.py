import unittest

from extract_tower_assets import STATS, boss_avatar, stats


class TowerTest(unittest.TestCase):
    def test_avatar_bosses_map_to_their_outfit(self):
        self.assertEqual(boss_avatar('AvatarUserFace_N90277'), 77)    # Aaroniero +2
        self.assertEqual(boss_avatar('AvatarUserFace_N91229'), 29)    # Halibel +2
        self.assertIsNone(boss_avatar('AvatarUserFace_N900163'))      # Kakuzu: not an avatar
        self.assertIsNone(boss_avatar('MapUserFace_N32053'))
        self.assertIsNone(boss_avatar('TGateUserFace_N4025111'))


    def test_late_floors_rate_dodge_block_and_crit_ten_times_higher(self):
        npcs = {column: ['', '7'] + ['0'] * 149 + ['256'] for column in STATS.values()}

        self.assertEqual(stats(npcs, 1)['dodge'], 7)
        self.assertEqual(stats(npcs, 151)['dodge'], 25)
        self.assertEqual(stats(npcs, 151)['crit'], 25)
        self.assertEqual(stats(npcs, 151)['max_hp'], 256)


if __name__ == '__main__':
    unittest.main()

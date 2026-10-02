import unittest

from extract_world_assets import areas

TIPS = {
    'lg_Tip_WorldMap_2101_0': "<font color='#00ffff'><font color='#fff67c'>  熔炉山地</font><br>进入等级： 1 级<br>"
    "场景怪物：<font color='#06ff00'>向日葵、蜇人蜂</font><br>场景BOSS：<font color='#ff0000'>黑暗武士</font></font>",
    'lg_Tip_WorldMap_2101_1': '你不属于火之村，有任务可以接取交付时才能进入此区域，其他时间无法进入！',
    'lg_Tip_WorldMap_111_0': '火之村封魔之战后建立的村庄。',
}


class WorldTest(unittest.TestCase):
    def test_areas_come_from_the_world_map_tooltips(self):
        self.assertEqual(areas(TIPS), [{
            'scene': '2101', 'name': '熔炉山地', 'level': 1,
            'monsters': ['向日葵', '蜇人蜂'], 'bosses': ['黑暗武士'],
        }])


if __name__ == '__main__':
    unittest.main()

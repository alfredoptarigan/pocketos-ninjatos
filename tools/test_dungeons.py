import unittest

from extract_dungeon_assets import dungeons


def npc(code, name, level, exp=''):
    stats = {column: '1' for column in ('MaxHP', 'MaxMP', 'MinAtk', 'MaxAtk', 'Defense', 'CritMul', 'CritAttach', 'DodgeMul', 'ParryMul', 'CounterMul', 'PriorityMul')}
    return {'ID': code, 'Name': name, 'Level': str(level), 'NpcExp': exp, **stats}


TABLES = {
    'tollgate': [
        {'ConfigID': '100001', 'Hard': '1', 'ConfigName': 'TollGate_100001', 'RequirLevel': '16', 'MaxLevel': '25',
         'TotalTimes': '3', 'RewardExp': '500', 'RewardMoney': '2800', 'TollGateMap': 'FristTollGatePic'},
        {'ConfigID': '100035', 'Hard': '3', 'ConfigName': 'TollGate_100035'},
    ],
    'subtollgate': [
        {'ID': '', 'ParentTollGateID': ''},
        {'ID': '200011', 'BeachheadName': 'SubTollGate_200011', 'Recommendlevel': '15-16', 'ParentTollGateID': '100001',
         'Seq': '0', 'RewardExp': '200', 'RewardMoney': '0,1100'},
    ],
    'fightmonsterpoint': [
        {'ID': '300112', 'ParentSubTollGateID': '200011', 'Seq': '1'},
        {'ID': '300111', 'ParentSubTollGateID': '200011', 'Seq': '0'},
    ],
    'monstergroup': [
        {'MonsterID': '300111', 'MonsterConfig1': 'n1', 'MonsterConfig2': 'n2', 'MonsterConfig3': 'n3'},
        {'MonsterID': '300112', 'MonsterConfig1': 'n4', 'MonsterConfig2': 'n5', 'MonsterConfig3': ''},
    ],
    'normalnpc': [npc('n1', 'name_a', 16), npc('n2', 'name_a', 16), npc('n3', 'name_b', 16, '30'),
                  npc('n4', 'name_a', 17), npc('n5', 'name_avatar45', 20)],
}
ENGLISH = {
    'lg_TollGate_100001': 'Valhalla Camp', 'lg_SubTollGate_200011': 'Camp Outpost',
    'lg_name_a': 'Demon Soldier', 'lg_name_b': 'Demon Guard', 'lg_name_avatar45': 'Uzumaki Naruto',
}


class DungeonTest(unittest.TestCase):
    def test_each_wave_sends_its_leader_and_the_last_wave_is_the_boss(self):
        [dungeon] = dungeons(TABLES, ENGLISH)

        self.assertEqual(dungeon['name'], 'Valhalla Camp')
        self.assertEqual(dungeon['difficulty'], 'hard')
        self.assertEqual(dungeon['reward_gold'], 2800)
        [stage] = dungeon['stages']
        self.assertEqual((stage['name'], stage['reward_gold']), ('Camp Outpost', 1100))
        self.assertEqual([(w['code'], w['is_boss'], w['exp']) for w in stage['waves']], [('n3', False, 30), ('n5', True, 60)])


if __name__ == '__main__':
    unittest.main()

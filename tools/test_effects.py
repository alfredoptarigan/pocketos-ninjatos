import tempfile
import unittest
from pathlib import Path

from extract_effect_assets import find_swf, origin_of, start_of

SVG = (
    '<svg ffdec:objectType="frame" height="361.45px" width="722.3px">'
    '<g transform="matrix(1.0, 0.0, 0.0, 1.0, 572.45, 224.45)"/></svg>'
)


class EffectTest(unittest.TestCase):
    def test_origin_adds_half_the_png_margin_to_the_svg_translate(self):
        with tempfile.TemporaryDirectory() as tmp:
            svg = Path(tmp) / '1.svg'
            svg.write_text(SVG)
            x, y = origin_of(svg, (837, 476))
        self.assertAlmostEqual(x, 629.8)
        self.assertAlmostEqual(y, 281.725)

    def test_start_of_reads_play_effect_time(self):
        self.assertEqual(start_of('MotionStart'), 0)
        self.assertEqual(start_of('ByAttacking'), 'hit')
        self.assertEqual(start_of('1000'), 1000)

    def test_find_swf_tries_part_and_letter_names(self):
        with tempfile.TemporaryDirectory() as tmp:
            folder = Path(tmp)
            for name in ('fighteffect_1807_1.s113.swf', 'fighteffect_3826_m.swf'):
                (folder / name).touch()
            self.assertEqual(find_swf(folder, 'FightEffect_1807_1', 'FightEffect_18071').name, 'fighteffect_1807_1.s113.swf')
            self.assertEqual(find_swf(folder, 'FightEffect_3826_M', 'FightEffect_38262').name, 'fighteffect_3826_m.swf')
            self.assertIsNone(find_swf(folder, 'FightEffect_3806', 'FightEffect_3806'))


if __name__ == '__main__':
    unittest.main()

import json
import tempfile
import unittest
from pathlib import Path

from PIL import Image

from import_custom_character import BODY_HEIGHT, FEET_BELOW_ORIGIN, HD_SCALE, Origin, custom_entry, frames, key_out_green, ultimate_id


def figure(canvas=(100, 200), box=(40, 100, 60, 180), color=(200, 50, 50, 255)):
    image = Image.new('RGBA', canvas)
    image.paste(Image.new('RGBA', (box[2] - box[0], box[3] - box[1]), color), box[:2])
    return image


class CustomCharacterTest(unittest.TestCase):
    def test_the_first_stance_frame_sets_the_scale_ground_and_centre(self):
        origin = Origin.of(figure())

        self.assertAlmostEqual(origin.factor, BODY_HEIGHT * HD_SCALE / 80)
        self.assertEqual(origin.x_ratio, 0.5)
        # Like the original sheets, the anchor sits FEET_BELOW_ORIGIN game px above the soles.
        self.assertAlmostEqual(origin.y_ratio, (180 - FEET_BELOW_ORIGIN * HD_SCALE / origin.factor) / 200)

    def test_frames_keep_their_place_on_the_canvas_relative_to_the_feet(self):
        origin = Origin.of(figure())
        # Same figure lunging 20 px left on the same canvas.
        (image, x, y), = frames([figure(box=(20, 100, 40, 180))], origin)

        self.assertAlmostEqual(y, -80 * origin.factor + FEET_BELOW_ORIGIN * HD_SCALE, delta=1)  # top of the body
        self.assertAlmostEqual(x, -30 * origin.factor, delta=1)  # 30 px left of the centre
        self.assertAlmostEqual(image.height, 80 * origin.factor, delta=1)

    def test_a_green_screen_turns_transparent(self):
        image = Image.new('RGBA', (2, 1))
        image.putdata([(0, 255, 0, 255), (200, 50, 50, 255)])

        self.assertEqual([pixel[3] for pixel in key_out_green(image).getdata()], [0, 255])

    def test_new_custom_characters_get_the_next_free_id_from_201(self):
        with tempfile.TemporaryDirectory() as tmp:
            data = Path(tmp) / 'custom_outfits.json'
            data.write_text(json.dumps([{'key': '0_201'}]))

            entry = custom_entry(data, name='Hero', sex=1, rarity='blue', weapon='gloves', key=None)

        self.assertEqual(entry, {'key': '1_202', 'name': 'Hero', 'sex': 1, 'rarity': 'blue', 'weapon_class': 'gloves'})
        self.assertEqual(ultimate_id('1_202'), 2102)


if __name__ == '__main__':
    unittest.main()

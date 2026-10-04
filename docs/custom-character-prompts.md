# Custom character prompts

Prompts for an image AI to redraw any character in the art style of this game
(the 2010 Flash MMO Pockie Ninja), and the frames the game needs. Paste a
section into the AI together with the images it asks for. When the frames are
done, `tools/import_custom_character.py` turns them into a playable outfit.

## How to use this file

1. Give the AI your character image as the **subject reference** and, if you
   can, one screenshot of a Pockie Ninja character as the **style reference**.
2. Run **Prompt 1** until you like the design. Save that image: it is the
   reference for everything else.
3. Run **Prompt 2** once for the face icon.
4. Run **Prompt 3** once per frame, always attaching the Prompt 1 image.
5. Put the frames in folders and run the importer (last section).

Use a tool that supports image references (character consistency). Without a
reference the character changes between frames.

## The style, in words

Include this block in any prompt you write yourself.

```
STYLE: chibi "super-deformed" game sprite in the style of the 2010 Flash MMO
"Pockie Ninja". About 2.2 heads tall; the head is 45% of the total height;
small body, short legs, small hands and feet. Large stylized hair silhouette
that reads at a tiny size. Big expressive anime eyes, tiny mouth, no nose
detail. Soft pre-rendered-3D shading: smooth gradients, a subtle highlight from
the top left, no hard cel bands, no pixel art. Thin dark brown outline.
Saturated but not neon colors. Outfit and accessories simplified to chibi
scale, keeping the colors and the 2-3 details that make the character
recognizable.
```

## Prompt 1: character design (the reference image)

```
Redesign the character from the attached image as a chibi game sprite.

STYLE: chibi "super-deformed" game sprite in the style of the 2010 Flash MMO
"Pockie Ninja". About 2.2 heads tall; the head is 45% of the total height;
small body, short legs, small hands and feet. Large stylized hair silhouette
that reads at a tiny size. Big expressive anime eyes, tiny mouth, no nose
detail. Soft pre-rendered-3D shading: smooth gradients, a subtle highlight from
the top left, no hard cel bands, no pixel art. Thin dark brown outline.
Saturated but not neon colors. Outfit and accessories simplified to chibi
scale, keeping the colors and the 2-3 details that make the character
recognizable.

POSE: full body, relaxed fighting stance, knees slightly bent, 3/4 view facing
LEFT, both feet flat on the ground.

OUTPUT: one character only, centered, the feet near the bottom of the canvas,
square canvas, flat pure green background (#00FF00), no shadow, no text, no
frame, no extra props, no second pose.
```

Keep the weapon out of this image unless the character is never seen without
it: the game draws the equipped weapon itself only for original outfits, so a
custom character shows whatever is painted into its frames.

## Prompt 2: face icon

```
Using the attached character design as the exact reference, draw a bust
portrait icon of the same character: head and shoulders only, 3/4 view facing
LEFT, the head filling most of the square. Same Pockie Ninja chibi style, same
colors, same soft 3D-like shading and thin dark brown outline. Square canvas,
flat pure green background (#00FF00), no text, no frame.
```

Save it as `face.png`.

## Prompt 3: battle frames

Run this once per frame. Replace the four placeholders before sending: `{N}`
(frame number), `{TOTAL}` (frames in the action), `{ACTION}` (folder name) and
`{POSE}` (the line for that frame under "Poses, frame by frame"). An AI that
receives the braces unfilled asks for the values instead of drawing.

```
Using the attached character design as the exact reference, draw frame {N} of
{TOTAL} of the character's {ACTION} animation: {POSE}.

Keep everything identical to the reference: proportions (2.2 heads tall),
colors, outfit details, outline and shading style. 3/4 view facing LEFT.
Same character size as the reference. Use the same square canvas for every
frame, with the ground line at the same height: when the feet touch the
ground they are exactly where they are in the reference. Flat pure green
background (#00FF00). One character, no shadow, no motion blur, no speed
lines, no text.
```

### Ready to paste: the first frame

Start with `stance` frame 1: it sets the size and where the character stands.

```
Using the attached character design as the exact reference, draw frame 1 of
4 of the character's stance animation: fighting stance, knees slightly bent,
fists raised.

Keep everything identical to the reference: proportions (2.2 heads tall),
colors, outfit details, outline and shading style. 3/4 view facing LEFT.
Same character size as the reference. Use the same square canvas for every
frame, with the ground line at the same height: when the feet touch the
ground they are exactly where they are in the reference. Flat pure green
background (#00FF00). One character, no shadow, no motion blur, no speed
lines, no text.
```

### Ready to paste: every next frame

In the same chat, the AI already has the rules. Send one short message per
frame, with the pose copied from "Poses, frame by frame":

```
Same rules and same reference. Frame 2 of 4 of the stance animation: same
pose, the body 2% lower (breathing out).
```

```
Same rules and same reference. Frame 3 of 6 of the attack animation: full
extension, the arm stretched out to the left, body leaning in.
```

Attach the reference image again, or start a new chat with the full prompt,
as soon as the colors, the size or the outfit start to drift.

### Frames to make

The original characters play at 12 frames per second.

| Folder   | Frames | Needed | What happens                                                |
| -------- | ------ | ------ | ----------------------------------------------------------- |
| `stance` | 4      | yes    | Battle stance, breathing: the body rises and falls slightly |
| `attack` | 6      | yes    | A strike towards the left, then back to the stance          |
| `dead`   | 1      | yes    | Knocked out, lying on the ground                            |
| `run`    | 4      | no     | Dash to the left                                            |
| `idle`   | 4      | no     | Relaxed standing (village and menus)                        |
| `dodge`  | 8      | no     | Back-flip away from the opponent                            |

Missing optional actions fall back to `stance` in the game.

### Poses, frame by frame

`stance` (loops)

1. Fighting stance, knees slightly bent, fists raised.
2. Same pose, the body 2% lower (breathing out).
3. Same pose, the body at its lowest point, shoulders relaxed.
4. Same pose, rising back, halfway to frame 1.

`attack`

1. Wind-up: weight on the back foot, the striking arm pulled back.
2. Stepping forward to the left, the arm starting to swing.
3. Full extension: the arm (or weapon) stretched out to the left, body leaning in.
4. Follow-through: the arm past the target, the body still leaning.
5. Recovering: stepping back, the arm coming home.
6. Back in the fighting stance.

`dead`

1. Lying on the back on the ground, head to the right, feet to the left, eyes closed.

`run` (loops)

1. Leaning forward to the left, right leg forward, arms swept back.
2. Both feet off the ground, legs passing each other.
3. Left leg forward, arms swept back.
4. Both feet off the ground, legs passing each other.

`idle` (loops)

1. Standing upright and relaxed, arms at the sides.
2. Same, the chest slightly raised (breathing in).
3. Same as frame 1.
4. Same, the head tilted one degree, blinking.

`dodge`

1. Crouching, ready to jump back.
2. Leaving the ground, leaning backwards.
3. Upside down in the air, a quarter of the flip.
4. Fully upside down, knees tucked.
5. Three quarters of the flip, the feet coming round.
6. Feet about to land, further to the right than the start.
7. Landing in a crouch.
8. Rising back into the fighting stance.

## Checklist before importing

- Every frame uses the same canvas size.
- The character faces left in every frame.
- The feet are on the same ground line in every standing frame.
- The character has the same height in every standing frame.
- The background is transparent or flat pure green (#00FF00).
- No frame has a shadow, text or a second character.

## Import into the game

```
my-character/
  face.png
  stance/01.png 02.png 03.png 04.png
  attack/01.png ... 06.png
  dead/01.png
  run/ idle/ dodge/        (optional)
```

```bash
python3 tools/import_custom_character.py my-character \
    --name "My Hero" --sex 0 --rarity orange --weapon sharp --green
php artisan db:seed --class=OutfitSeeder
```

- `--sex` 0 is male, 1 is female (the wardrobe is split by sex).
- `--weapon` is the weapon class the outfit may equip: `sharp`, `blunt`,
  `gloves` or `all`.
- `--green` removes the green background; leave it out for transparent PNGs.
- `--fps attack=24` plays one action faster.
- `--key 0_201` updates a character imported earlier.

The first `stance` frame sets the size and where the character stands, so make
that frame clean. The importer scales the character to the height of the
original outfits (102 px, stored at 2x).

A custom character has no ultimate cinematic: its Secret Technique plays as a
plain strike, with the face as its icon.

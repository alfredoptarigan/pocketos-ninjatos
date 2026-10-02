import { Assets, ColorMatrixFilter, Container, Sprite, Text } from 'pixi.js';
import type { Texture } from 'pixi.js';

// Pixels at least this opaque count as part of a spot for hit testing.
const HIT_ALPHA_THRESHOLD = 128;
const HOVER_BRIGHTNESS = 1.35;

export type Spot = { image: string; x: number; y: number };

/** A picture with clickable cut-outs on top (village buildings, world map areas). */
export type HotspotData = {
    background: string;
    width: number;
    height: number;
    spots: Record<string, Spot>;
};

export type HotspotOptions = {
    label: (key: string) => string;
    labelSize: number;
    onSelect: (key: string) => void;
    onHover?: (key: string | null) => void;
};

/** Hit area that only counts opaque pixels, so overlapping cut-outs pick the right spot. */
function alphaHitArea(texture: Texture) {
    const { width, height } = texture;
    const canvas = new OffscreenCanvas(width, height);
    const context = canvas.getContext('2d');

    if (!context) {
        throw new Error('2D canvas is not available for hit testing');
    }

    context.drawImage(texture.source.resource as CanvasImageSource, 0, 0);
    const alpha = context.getImageData(0, 0, width, height).data;

    return {
        contains(x: number, y: number): boolean {
            const px = Math.floor(x);
            const py = Math.floor(y);

            if (px < 0 || py < 0 || px >= width || py >= height) {
                return false;
            }

            return alpha[(py * width + px) * 4 + 3] >= HIT_ALPHA_THRESHOLD;
        },
    };
}

async function createSpot(
    key: string,
    spot: Spot,
    options: HotspotOptions,
): Promise<{ sprite: Sprite; label: Text }> {
    const texture = await Assets.load<Texture>(spot.image);
    const sprite = new Sprite(texture);
    const glow = new ColorMatrixFilter();
    glow.brightness(HOVER_BRIGHTNESS, false);

    const label = new Text({
        text: options.label(key),
        style: {
            fontSize: options.labelSize,
            fill: 0xfff4d6,
            fontWeight: 'bold',
            stroke: { color: 0x3b1d0a, width: options.labelSize / 5 },
        },
    });
    label.anchor.set(0.5);
    label.position.set(spot.x + texture.width / 2, spot.y + texture.height / 2);
    label.visible = false;
    label.eventMode = 'none';

    sprite.position.set(spot.x, spot.y);
    sprite.eventMode = 'static';
    sprite.cursor = 'pointer';
    sprite.hitArea = alphaHitArea(texture);
    sprite.on('pointerover', () => {
        sprite.filters = [glow];
        label.visible = true;
        options.onHover?.(key);
    });
    sprite.on('pointerout', () => {
        sprite.filters = [];
        label.visible = false;
        options.onHover?.(null);
    });
    sprite.on('pointertap', () => options.onSelect(key));

    return { sprite, label };
}

export async function createHotspotScene(
    data: HotspotData,
    options: HotspotOptions,
): Promise<Container> {
    const world = new Container();
    const background = new Sprite(await Assets.load(data.background));
    background.setSize(data.width, data.height);

    // SWF depth order: later spots sit on top and win overlapping clicks.
    const spots = await Promise.all(
        Object.entries(data.spots).map(([key, spot]) =>
            createSpot(key, spot, options),
        ),
    );
    world.addChild(background, ...spots.map((spot) => spot.sprite));
    // Labels go above every spot so a hovered name is never covered.
    world.addChild(...spots.map((spot) => spot.label));

    return world;
}

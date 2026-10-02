import { Assets, ColorMatrixFilter, Container, Sprite, Text } from 'pixi.js';
import type { Texture } from 'pixi.js';

// Original Pockie Ninja main-city backgrounds are 1920x1080 and building
// coordinates in villages.json are in that space.
export const WORLD_WIDTH = 1920;
export const WORLD_HEIGHT = 1080;

const VILLAGES_URL = '/game-assets/villages.json';
// Pixels at least this opaque count as part of a building for hit testing.
const HIT_ALPHA_THRESHOLD = 128;
const HOVER_BRIGHTNESS = 1.35;

const BUILDING_NAMES: Record<string, string> = {
    hall: 'Mission Hall',
    salve: 'Pharmacy',
    foundry: 'Blacksmith',
    equip: 'Equipment Shop',
    reward: 'Bounty Board',
    pet: 'Pet Shop',
    arena: 'Arena',
    CardLink: 'Card Hall',
    meetingroom: 'Council Room',
    RandPot: 'Lucky Pot',
    nation: 'War Hall',
    middle: 'Nation Square',
};

type Building = { image: string; x: number; y: number };
type VillageData = { background: string; buildings: Record<string, Building> };

export function buildingName(key: string): string {
    return BUILDING_NAMES[key] ?? key;
}

/** Hit area that only counts opaque pixels, so overlapping cut-outs pick the right building. */
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

function createBuildingLabel(
    key: string,
    building: Building,
    texture: Texture,
): Text {
    const label = new Text({
        text: buildingName(key),
        style: {
            fontSize: 30,
            fill: 0xfff4d6,
            fontWeight: 'bold',
            stroke: { color: 0x3b1d0a, width: 6 },
        },
    });
    label.anchor.set(0.5);
    label.position.set(
        building.x + texture.width / 2,
        building.y + texture.height / 2,
    );
    label.visible = false;
    label.eventMode = 'none';
    return label;
}

async function createBuilding(
    key: string,
    building: Building,
    onSelect: (key: string) => void,
): Promise<{ sprite: Sprite; label: Text }> {
    const texture = await Assets.load<Texture>(building.image);
    const sprite = new Sprite(texture);
    const label = createBuildingLabel(key, building, texture);
    const glow = new ColorMatrixFilter();
    glow.brightness(HOVER_BRIGHTNESS, false);

    sprite.position.set(building.x, building.y);
    sprite.eventMode = 'static';
    sprite.cursor = 'pointer';
    sprite.hitArea = alphaHitArea(texture);
    sprite.on('pointerover', () => {
        sprite.filters = [glow];
        label.visible = true;
    });
    sprite.on('pointerout', () => {
        sprite.filters = [];
        label.visible = false;
    });
    sprite.on('pointertap', () => onSelect(key));

    return { sprite, label };
}

/** Load a village from the extracted assets and build its scene. */
export async function createVillage(
    villageId: string,
    onSelect: (key: string) => void,
): Promise<Container> {
    const villages =
        await Assets.load<Record<string, VillageData>>(VILLAGES_URL);
    const village = villages[villageId];

    if (!village) {
        throw new Error(`Village ${villageId} is missing from ${VILLAGES_URL}`);
    }

    const world = new Container();
    const background = new Sprite(await Assets.load(village.background));
    background.setSize(WORLD_WIDTH, WORLD_HEIGHT);

    // SWF depth order: later buildings sit on top and win overlapping clicks.
    const buildings = await Promise.all(
        Object.entries(village.buildings).map(([key, building]) =>
            createBuilding(key, building, onSelect),
        ),
    );
    world.addChild(background, ...buildings.map((b) => b.sprite));
    // Labels go above every building so a hovered name is never covered.
    world.addChild(...buildings.map((b) => b.label));

    return world;
}

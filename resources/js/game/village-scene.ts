import { Assets, Container, Graphics, Sprite, Text } from 'pixi.js';

// Original Pockie Ninja main-city backgrounds are 1920x1080 and building
// coordinates in villages.json are in that space.
export const WORLD_WIDTH = 1920;
export const WORLD_HEIGHT = 1080;

const VILLAGES_URL = '/game-assets/villages.json';

const BUILDING_NAMES: Record<string, string> = {
    arena: 'Arena',
    pharmacy: 'Pharmacy',
    hall: 'Mission Hall',
    equip: 'Equipment Shop',
    foundry: 'Blacksmith',
    trade: 'Market',
    rest: 'Inn',
};

type Building = { x: number; y: number };
type VillageData = { background: string; buildings: Record<string, Building> };

export function buildingName(key: string): string {
    return BUILDING_NAMES[key] ?? key;
}

function createBuildingLabel(
    key: string,
    building: Building,
    onSelect: (key: string) => void,
): Container {
    const label = new Container();
    const text = new Text({
        text: buildingName(key),
        style: {
            fontSize: 26,
            fill: 0xfff4d6,
            fontWeight: 'bold',
            stroke: { color: 0x3b1d0a, width: 5 },
        },
    });
    const paddingX = 18;
    const paddingY = 8;
    const plate = new Graphics()
        .roundRect(
            0,
            0,
            text.width + paddingX * 2,
            text.height + paddingY * 2,
            12,
        )
        .fill({ color: 0x7a2e12, alpha: 0.85 })
        .stroke({ width: 3, color: 0xf2c14e });
    text.position.set(paddingX, paddingY);
    label.addChild(plate, text);
    label.position.set(building.x, building.y);

    label.eventMode = 'static';
    label.cursor = 'pointer';
    label.on('pointerover', () => label.scale.set(1.08));
    label.on('pointerout', () => label.scale.set(1));
    label.on('pointertap', () => onSelect(key));

    return label;
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
    world.addChild(background);

    Object.entries(village.buildings).forEach(([key, building]) => {
        world.addChild(createBuildingLabel(key, building, onSelect));
    });

    return world;
}

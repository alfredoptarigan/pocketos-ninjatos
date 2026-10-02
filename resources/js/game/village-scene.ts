import { Assets } from 'pixi.js';
import type { Container } from 'pixi.js';
import { createHotspotScene } from './hotspot-scene';
import type { Spot } from './hotspot-scene';

// Original Pockie Ninja main-city backgrounds are 1920x1080 and building
// coordinates in villages.json are in that space.
export const WORLD_WIDTH = 1920;
export const WORLD_HEIGHT = 1080;

const VILLAGES_URL = '/game-assets/villages.json';
const LABEL_SIZE = 30;

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

type VillageData = { background: string; buildings: Record<string, Spot> };

export function buildingName(key: string): string {
    return BUILDING_NAMES[key] ?? key;
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

    return createHotspotScene(
        {
            background: village.background,
            width: WORLD_WIDTH,
            height: WORLD_HEIGHT,
            spots: village.buildings,
        },
        { label: buildingName, labelSize: LABEL_SIZE, onSelect },
    );
}

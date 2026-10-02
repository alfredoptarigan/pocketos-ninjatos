import { Container, Graphics, Rectangle, Text } from 'pixi.js';
import type { Point } from './movement';

// Fixed world size; the stage is scaled to fit whatever box the canvas gets.
export const WORLD_WIDTH = 1200;
export const WORLD_HEIGHT = 700;
export const NINJA_SPAWN: Point = { x: 600, y: 420 };

const HOUSES = [
    { x: 140, y: 120, w: 170, h: 110, wall: 0xd9b382, roof: 0xb4442f },
    {
        x: 470,
        y: 90,
        w: 220,
        h: 130,
        wall: 0xe3c79b,
        roof: 0x7a3b2e,
        label: 'Balai Desa',
    },
    {
        x: 880,
        y: 130,
        w: 160,
        h: 100,
        wall: 0xd9b382,
        roof: 0x3f5f8a,
        label: 'Toko',
    },
    { x: 180, y: 470, w: 150, h: 95, wall: 0xcfa676, roof: 0x5b7a3a },
];

const TREES: Point[] = [
    { x: 60, y: 330 },
    { x: 380, y: 300 },
    { x: 760, y: 300 },
    { x: 1120, y: 360 },
    { x: 1060, y: 600 },
    { x: 470, y: 640 },
    { x: 60, y: 640 },
    { x: 1150, y: 80 },
];

function drawGround(): Graphics {
    const g = new Graphics();
    g.rect(0, 0, WORLD_WIDTH, WORLD_HEIGHT).fill(0x6fa553);
    // Crossing dirt roads.
    g.rect(0, 380, WORLD_WIDTH, 80).fill(0xc8a46a);
    g.rect(560, 0, 80, WORLD_HEIGHT).fill(0xc8a46a);
    // Pond.
    g.ellipse(880, 570, 150, 75).fill(0x4f8fc9);
    g.ellipse(880, 570, 150, 75).stroke({ width: 6, color: 0x3c6f9e });
    return g;
}

function drawHouse(house: (typeof HOUSES)[number]): Container {
    const c = new Container();
    const g = new Graphics();
    const roofHeight = house.h * 0.6;
    g.rect(house.x, house.y + roofHeight, house.w, house.h).fill(house.wall);
    g.poly([
        house.x - 15,
        house.y + roofHeight,
        house.x + house.w / 2,
        house.y,
        house.x + house.w + 15,
        house.y + roofHeight,
    ]).fill(house.roof);
    g.rect(
        house.x + house.w / 2 - 15,
        house.y + roofHeight + house.h - 45,
        30,
        45,
    ).fill(0x5a3a22);
    c.addChild(g);

    if (house.label) {
        const label = new Text({
            text: house.label,
            style: { fontSize: 16, fill: 0xffffff, fontWeight: 'bold' },
        });
        label.anchor.set(0.5);
        label.position.set(house.x + house.w / 2, house.y + roofHeight + 22);
        c.addChild(label);
    }

    return c;
}

function drawTree({ x, y }: Point): Graphics {
    const g = new Graphics();
    g.rect(x - 7, y, 14, 30).fill(0x6b4423);
    g.circle(x, y - 10, 32).fill(0x2f6b2f);
    g.circle(x - 12, y - 20, 18).fill(0x3d8a3d);
    return g;
}

/** Simple placeholder ninja until real sprites are converted from the SWF assets. */
export function createNinja(name: string): Container {
    const ninja = new Container();
    const body = new Graphics();
    body.ellipse(0, 4, 18, 6).fill({ color: 0x000000, alpha: 0.25 }); // shadow
    body.roundRect(-14, -34, 28, 36, 8).fill(0x1f2a44); // outfit
    body.circle(0, -46, 15).fill(0xf2c79b); // head
    body.rect(-16, -54, 32, 7).fill(0x2d6cdf); // headband
    body.rect(-5, -53, 10, 5).fill(0xc0c7d6); // forehead plate
    ninja.addChild(body);

    const label = new Text({
        text: name,
        style: {
            fontSize: 14,
            fill: 0xffffff,
            fontWeight: 'bold',
            stroke: { color: 0x000000, width: 3 },
        },
    });
    label.anchor.set(0.5, 1);
    label.y = -66;
    ninja.addChild(label);

    return ninja;
}

export function createTargetMarker(): Graphics {
    const g = new Graphics();
    g.circle(0, 0, 10).stroke({ width: 3, color: 0xffffff, alpha: 0.9 });
    g.visible = false;
    return g;
}

/** Build the static village world. The returned container is also the click target. */
export function createVillage(): Container {
    const world = new Container();
    world.addChild(drawGround());
    HOUSES.forEach((house) => world.addChild(drawHouse(house)));
    TREES.forEach((tree) => world.addChild(drawTree(tree)));
    world.eventMode = 'static';
    world.hitArea = new Rectangle(0, 0, WORLD_WIDTH, WORLD_HEIGHT);
    world.cursor = 'pointer';
    return world;
}

import { AnimatedSprite, Assets, Container, Text, Ticker } from 'pixi.js';
import type { Spritesheet } from 'pixi.js';
import type { MonsterArt } from './battle/types';
import { createHotspotScene } from './hotspot-scene';
import type { Spot } from './hotspot-scene';

// Original out-city backdrops are 1920x1080; spot coordinates are in that space.
export const FIELD_WIDTH = 1920;
export const FIELD_HEIGHT = 1080;

const LABEL_SIZE = 30;
const MONSTER_SCALE = 2.2;
const GROUND_Y = 880;
const ROW_OFFSET = 70;
const MARGIN_X = 260;
const WANDER_RANGE = 180;
const WANDER_SPEED = 90; // world pixels per second
const REST_MS = [2500, 6000];
const TICKER_FPS = 60;
const MOTION_FPS = 12;
const LABEL_HEIGHT_SHARE = 0.55;

export type FieldMonster = {
    id: number;
    name: string;
    level: number;
    is_boss: boolean;
    art: MonsterArt;
};

type Options = {
    spotLabel: (spot: string) => string;
    onSpot: (spot: string) => void;
    onMonster: (id: number) => void;
};

function randomBetween([low, high]: number[]): number {
    return low + Math.random() * (high - low);
}

/** Stroll left and right around `home`, resting in between; stops when the sprite is destroyed. */
function wander(
    body: AnimatedSprite,
    sheet: Spritesheet,
    view: Container,
    home: number,
): void {
    let target = home;
    let restUntil = performance.now() + randomBetween(REST_MS);
    const play = (action: 'run' | 'stance') => {
        const textures = sheet.animations[action] ?? sheet.animations.stance;

        if (body.textures !== textures) {
            body.textures = textures;
            body.play();
        }
    };

    const step = (ticker: Ticker) => {
        if (view.destroyed) {
            Ticker.shared.remove(step);
            return;
        }

        if (performance.now() < restUntil) {
            return;
        }

        if (target === view.x) {
            target = home + randomBetween([-WANDER_RANGE, WANDER_RANGE]);
            // Motions are drawn facing left.
            body.scale.x = (target > view.x ? -1 : 1) * Math.abs(body.scale.x);
            play('run');
        }

        const move = (WANDER_SPEED * ticker.deltaMS) / 1000;
        const left = target - view.x;
        view.x =
            Math.abs(left) <= move ? target : view.x + Math.sign(left) * move;

        if (view.x === target) {
            play('stance');
            restUntil = performance.now() + randomBetween(REST_MS);
        }
    };
    Ticker.shared.add(step);
}

async function createMonster(
    monster: FieldMonster,
    x: number,
    y: number,
    onSelect: () => void,
): Promise<Container | null> {
    if (monster.art.type !== 'motion') {
        return null;
    }

    const sheet = await Assets.load<Spritesheet>(monster.art.motions);
    const body = new AnimatedSprite({
        textures: sheet.animations.stance,
        updateAnchor: true,
    });
    body.scale.set(MONSTER_SCALE);
    body.animationSpeed = MOTION_FPS / TICKER_FPS;
    body.play();

    const label = new Text({
        text: `Lv.${monster.level} ${monster.name}`,
        style: {
            fontSize: 26,
            fontWeight: 'bold',
            fill: monster.is_boss ? 0xff6b6b : 0xfff4d6,
            stroke: { color: 0x1a0f05, width: 6 },
        },
    });
    label.anchor.set(0.5, 1);
    // Motion frames are sized for the whole attack, so the body fills only part of them.
    label.y = -body.height * LABEL_HEIGHT_SHARE;

    const view = new Container();
    view.addChild(body, label);
    view.position.set(x, y);
    view.eventMode = 'static';
    view.cursor = 'pointer';
    view.on('pointertap', onSelect);
    wander(body, sheet, view, x);

    return view;
}

/** A hunting ground: the original backdrop, its search spots and its monsters roaming the ground. */
export async function createField(
    data: {
        background: string;
        spots: Record<string, Spot>;
        monsters: FieldMonster[];
    },
    options: Options,
): Promise<Container> {
    const world = await createHotspotScene(
        {
            background: data.background,
            width: FIELD_WIDTH,
            height: FIELD_HEIGHT,
            spots: data.spots,
        },
        {
            label: options.spotLabel,
            labelSize: LABEL_SIZE,
            onSelect: options.onSpot,
        },
    );
    const gap =
        (FIELD_WIDTH - 2 * MARGIN_X) / Math.max(1, data.monsters.length - 1);
    const monsters = await Promise.all(
        data.monsters.map((monster, index) =>
            createMonster(
                monster,
                MARGIN_X + index * gap,
                GROUND_Y + (index % 2) * ROW_OFFSET,
                () => options.onMonster(monster.id),
            ),
        ),
    );
    // Nearer (lower) monsters are drawn over farther ones.
    world.addChild(
        ...monsters
            .filter((m): m is Container => m !== null)
            .sort((a, b) => a.y - b.y),
    );

    return world;
}

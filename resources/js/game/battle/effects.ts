import { AnimatedSprite, Assets } from 'pixi.js';
import type { Container, Spritesheet, Ticker } from 'pixi.js';
import type { BattleEvent } from './types';

// Written by tools/extract_effect_assets.py from the original effectconfig table.
const INDEX_URL = '/game-assets/effects/index.json';
const TICKER_FPS = 60;
const DEFAULT_FPS = 12;

export type EffectSpec = {
    sheet: string;
    /** 'attack' plays at the jutsu user, 'beaten' at whoever it hits. */
    type: 'attack' | 'beaten';
    layer: 'before' | 'under';
    /** ms after the cast, or 'hit' for the moment of impact. */
    start: number | 'hit';
};

export type EffectIndex = Record<string, EffectSpec[]>;

export type Stage = {
    ticker: Ticker;
    world: Container;
    speed: () => number;
    /** Index in `world` where effects drawn under the fighters go. */
    underIndex: number;
};

/**
 * Load the effect index and every sheet this battle uses. Effects are
 * optional art: missing files only mean a jutsu plays without them.
 */
export async function loadEffects(events: BattleEvent[]): Promise<EffectIndex> {
    const used = new Set(
        events.flatMap((event) => [
            'skill' in event ? event.skill : undefined,
            'blockSkill' in event ? event.blockSkill : undefined,
        ]),
    );

    try {
        const response = await fetch(INDEX_URL);
        const index: EffectIndex = response.ok ? await response.json() : {};
        const wanted = Object.fromEntries(
            Object.entries(index).filter(([id]) => used.has(id)),
        );
        await Promise.all(
            Object.values(wanted)
                .flat()
                .map((spec) => Assets.load<Spritesheet>(spec.sheet)),
        );

        return wanted;
    } catch {
        return {};
    }
}

/**
 * Jutsu whose art starts with the cast are thrown from where the user
 * stands: the original art already travels the ~400 units between fighters.
 */
export function isRanged(specs: EffectSpec[]): boolean {
    return specs.some((spec) => spec.type === 'attack' && spec.start !== 'hit');
}

function fpsOf(sheet: Spritesheet): number {
    return (sheet.data.meta as { fps?: number }).fps ?? DEFAULT_FPS;
}

/** How long an effect runs at 1x speed, in ms. */
export function effectDuration(spec: EffectSpec): number {
    const sheet = Assets.get<Spritesheet | undefined>(spec.sheet);

    return sheet ? (sheet.animations.effect.length / fpsOf(sheet)) * 1000 : 0;
}

/**
 * Play one effect with its origin at `x, y`; resolves when it ends.
 * Effects are drawn facing left, like the motions.
 */
export function playEffect(
    stage: Stage,
    spec: EffectSpec,
    x: number,
    y: number,
    facingRight: boolean,
): Promise<void> {
    const sheet = Assets.get<Spritesheet | undefined>(spec.sheet);

    if (!sheet) {
        return Promise.resolve();
    }

    const sprite = new AnimatedSprite({
        textures: sheet.animations.effect,
        updateAnchor: true,
        loop: false,
    });
    // Original stage units: the art is laid out for the original fighter spacing.
    sprite.scale.x = facingRight ? -1 : 1;
    sprite.position.set(x, y);
    sprite.animationSpeed = (fpsOf(sheet) / TICKER_FPS) * stage.speed();

    if (spec.layer === 'under') {
        stage.world.addChildAt(sprite, stage.underIndex);
    } else {
        stage.world.addChild(sprite);
    }

    return new Promise((resolve) => {
        sprite.onComplete = () => {
            sprite.destroy();
            resolve();
        };
        sprite.play();
    });
}

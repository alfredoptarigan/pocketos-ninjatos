import { AnimatedSprite, Assets, Graphics } from 'pixi.js';
import type { Container, Spritesheet, Texture, Ticker } from 'pixi.js';
import type { BattleEvent } from './types';

// Written by tools/extract_effect_assets.py from the original effectconfig table.
const INDEX_URL = '/game-assets/effects/index.json';
const TICKER_FPS = 60;
const DEFAULT_FPS = 12;
// The original blacked out the stage; keep the fighters faintly visible.
const SHADE_ALPHA = 0.85;

export type EffectSpec = {
    /** Pages of one effect, played in order (big cinematics need several 4096 textures). */
    sheets?: string[];
    /** Older indexes: a single page. */
    sheet?: string;
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

/** Outfits at +19 and up play the stronger cinematic of their ultimate. */
export function upgradedUltimate(skillId: string): string {
    return `${skillId}0`;
}

/**
 * Load the effect index and every sheet this battle uses. Effects are
 * optional art: missing files only mean a jutsu plays without them.
 */
export async function loadEffects(
    events: BattleEvent[],
    /** Jutsu id => jutsu whose effect it borrows (config('skills.skills.*.art')). */
    borrowed: Record<string, string> = {},
): Promise<EffectIndex> {
    const used = new Set(
        events.flatMap((event) => [
            event.type === 'ultimate'
                ? undefined
                : 'skill' in event
                  ? event.skill
                  : undefined,
            'blockSkill' in event ? event.blockSkill : undefined,
        ]),
    );

    try {
        const response = await fetch(INDEX_URL);
        const original: EffectIndex = response.ok ? await response.json() : {};
        const index: EffectIndex = {
            ...original,
            ...Object.fromEntries(
                Object.entries(borrowed)
                    .filter(([id, art]) => !original[id] && original[art])
                    .map(([id, art]) => [id, original[art]]),
            ),
        };
        // Only the cinematic an ultimate will play: the +19 one when it exists.
        events.forEach((event) => {
            if (event.type === 'ultimate') {
                const upgraded = upgradedUltimate(event.skill);
                used.add(
                    event.upgraded && index[upgraded] ? upgraded : event.skill,
                );
            }
        });
        const wanted = Object.fromEntries(
            Object.entries(index).filter(([id]) => used.has(id)),
        );
        await Promise.all(
            Object.values(wanted)
                .flat()
                .flatMap(pagesOf)
                .map((page) => Assets.load<Spritesheet>(page)),
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

type EffectMeta = { fps?: number; backdrop?: number[] };

function pagesOf(spec: EffectSpec): string[] {
    return spec.sheets ?? (spec.sheet ? [spec.sheet] : []);
}

/** Every frame of an effect across its pages, and the first page's meta (fps, backdrop frames). */
function framesOf(
    spec: EffectSpec,
): { textures: Texture[]; meta: EffectMeta } | null {
    const pages = pagesOf(spec).map((page) =>
        Assets.get<Spritesheet | undefined>(page),
    );

    if (pages.length === 0 || pages.some((page) => !page)) {
        return null;
    }

    const loaded = pages as Spritesheet[];

    return {
        textures: loaded.flatMap((page) => page.animations.effect),
        meta: loaded[0].data.meta as EffectMeta,
    };
}

/** A dark cover over the whole battle world, for frames that darkened the original stage. */
function shadeOver(world: Container): Graphics {
    const { x, y, width, height } = world.getLocalBounds();

    return new Graphics()
        .rect(x, y, width, height)
        .fill({ color: 0x000000, alpha: SHADE_ALPHA });
}

/** How long an effect runs at 1x speed, in ms. */
export function effectDuration(spec: EffectSpec): number {
    const frames = framesOf(spec);

    return frames
        ? (frames.textures.length / (frames.meta.fps ?? DEFAULT_FPS)) * 1000
        : 0;
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
    const frames = framesOf(spec);

    if (!frames) {
        return Promise.resolve();
    }

    const sprite = new AnimatedSprite({
        textures: frames.textures,
        updateAnchor: true,
        loop: false,
    });
    // Original stage units: the art is laid out for the original fighter spacing.
    sprite.scale.x = facingRight ? -1 : 1;
    sprite.position.set(x, y);
    sprite.animationSpeed =
        ((frames.meta.fps ?? DEFAULT_FPS) / TICKER_FPS) * stage.speed();

    const backdrop = frames.meta.backdrop ?? [];
    const shade = backdrop.length > 0 ? shadeOver(stage.world) : undefined;
    // The shade sits right below the effect, covering whatever it covered.
    const layers = shade ? [shade, sprite] : [sprite];

    if (spec.layer === 'under') {
        layers.forEach((layer, offset) =>
            stage.world.addChildAt(layer, stage.underIndex + offset),
        );
    } else {
        stage.world.addChild(...layers);
    }

    if (shade) {
        shade.visible = backdrop.includes(0);
        sprite.onFrameChange = (frame) => {
            shade.visible = backdrop.includes(frame);
        };
    }

    return new Promise((resolve) => {
        sprite.onComplete = () => {
            shade?.destroy();
            sprite.destroy();
            resolve();
        };
        sprite.play();
    });
}

import { AnimatedSprite, Assets, Container, Sprite } from 'pixi.js';
import type { Spritesheet, Texture } from 'pixi.js';
import { characterAssets } from '@/types/game';
import type { FighterInfo } from './types';

// Pixi's ticker runs at 60 updates per second; SWF motions have their own fps.
const TICKER_FPS = 60;
const DEFAULT_FPS = 12;
const MOTION_SCALE = 1.7;
const PORTRAIT_SCALE = 1.15;

export type Action = 'idle' | 'stance' | 'run' | 'attack' | 'dodge' | 'dead';

/** One fighter on the battle stage. Names and health live in the battle HUD, as in the original. */
export class Fighter {
    readonly view = new Container();

    private constructor(
        readonly info: FighterInfo,
        private readonly body: AnimatedSprite | Sprite,
        private readonly sheet: Spritesheet | null,
    ) {
        body.scale.set(sheet ? MOTION_SCALE : PORTRAIT_SCALE);
        // Original motions are drawn facing left; mirror opponents so they face the player.
        if (sheet && !info.avatar) {
            body.scale.x *= -1;
        }
        this.view.addChild(body);
    }

    /** The player uses their avatar's motions; opponents use monster motions or a boss portrait. */
    static async create(info: FighterInfo): Promise<Fighter> {
        const motions = info.avatar
            ? characterAssets(info.avatar).motions
            : info.art?.type === 'motion'
              ? info.art.motions
              : null;

        if (motions) {
            const sheet = await Assets.load<Spritesheet>(motions);
            const body = new AnimatedSprite({
                textures: sheet.animations.stance,
                updateAnchor: true,
            });

            return new Fighter(info, body, sheet);
        }

        const portrait = info.art?.type === 'portrait' ? info.art.portrait : '';
        const body = new Sprite(await Assets.load<Texture>(portrait));
        body.anchor.set(0.5, 1);

        return new Fighter(info, body, null);
    }

    get isAnimated(): boolean {
        return this.sheet !== null;
    }

    /** Play an action; resolves when a one-shot action ends (immediately for loops). */
    play(action: Action, speed: number, loop = false): Promise<void> {
        if (!(this.body instanceof AnimatedSprite) || !this.sheet) {
            return Promise.resolve();
        }

        const body = this.body;
        const name = this.sheet.animations[action] ? action : 'stance';
        const fps =
            (this.sheet.data.meta as { fps?: Record<string, number> }).fps?.[
                name
            ] ?? DEFAULT_FPS;
        body.textures = this.sheet.animations[name];
        body.animationSpeed = (fps / TICKER_FPS) * speed;
        body.loop = loop;

        return new Promise((resolve) => {
            body.onComplete = () => resolve();
            body.gotoAndPlay(0);

            if (loop) {
                resolve();
            }
        });
    }

    /** Brief red flash when hit. */
    flash(): void {
        this.body.tint = 0xff6b6b;
        setTimeout(() => (this.body.tint = 0xffffff), 140);
    }

    fadeOut(): void {
        this.body.alpha = 0.35;
    }

    get height(): number {
        return this.body.height;
    }
}

import { AnimatedSprite, Assets, Container, Sprite, Texture } from 'pixi.js';
import type { Spritesheet } from 'pixi.js';
import { characterAssets, weaponMotions } from '@/types/game';
import type { FighterInfo } from './types';

// Pixi's ticker runs at 60 updates per second; SWF motions have their own fps.
const TICKER_FPS = 60;
const DEFAULT_FPS = 12;
// Motions at their original size: small fighters as in the original client
// (the sheets carry 2x HD pixels, so they stay sharp when the stage scales up).
const MOTION_SCALE = 1;
// Bosses without battle art stand in as a portrait about as tall as a fighter.
const PORTRAIT_HEIGHT = 180;
const PORTRAIT_ANCHOR_Y = 0.82;

export type Action = 'idle' | 'stance' | 'run' | 'attack' | 'dodge' | 'dead';

/** One fighter on the battle stage. Names and health live in the battle HUD, as in the original. */
export class Fighter {
    readonly view = new Container();

    private constructor(
        readonly info: FighterInfo,
        private readonly body: AnimatedSprite | Sprite,
        private readonly sheet: Spritesheet | null,
        private readonly weapon: Weapon | null = null,
    ) {
        body.scale.set(
            sheet ? MOTION_SCALE : PORTRAIT_HEIGHT / body.texture.height,
        );
        // Original motions are drawn facing left; mirror the player (on the
        // left) so they face the opponent.
        if (sheet && info.avatar) {
            body.scale.x *= -1;
        }
        this.view.addChild(body);

        if (weapon) {
            weapon.sprite.scale.copyFrom(body.scale);
            this.view.addChild(weapon.sprite);
        }
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

            const weapon =
                info.avatar && info.weapon
                    ? await loadWeapon(info.avatar, info.weapon)
                    : null;

            return new Fighter(info, body, sheet, weapon);
        }

        const portrait = info.art?.type === 'portrait' ? info.art.portrait : '';
        const texture = await Assets.load<Texture>(portrait);
        const body = new Sprite(texture);
        // Busts cut off at the bottom: sink the cut below the ground line
        // and fade every edge so it does not read as a pasted box.
        body.anchor.set(0.5, PORTRAIT_ANCHOR_Y);
        const fade = new Sprite(vignette());
        fade.anchor.set(0.5, PORTRAIT_ANCHOR_Y);
        fade.setSize(texture.width, texture.height);
        body.addChild(fade);
        body.mask = fade;

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
        this.playWeapon(name, body);

        return new Promise((resolve) => {
            body.onComplete = () => resolve();
            body.gotoAndPlay(0);

            if (loop) {
                resolve();
            }
        });
    }

    /** The weapon follows the body tick for tick; idle and dodge have no weapon art. */
    private playWeapon(action: string, body: AnimatedSprite): void {
        if (!this.weapon) {
            return;
        }

        const { sprite, sheet } = this.weapon;
        const textures = sheet.animations[action];
        sprite.visible = Boolean(textures);

        if (textures) {
            sprite.textures = textures;
            sprite.animationSpeed = body.animationSpeed;
            sprite.loop = body.loop;
            sprite.gotoAndPlay(0);
        }
    }

    /** Brief red flash when hit. */
    flash(): void {
        this.view.tint = 0xff6b6b;
        setTimeout(() => (this.view.tint = 0xffffff), 140);
    }

    fadeOut(): void {
        this.view.alpha = 0.35;
    }

    /** Undo a knock-out (a revive jutsu). */
    restore(): void {
        this.view.alpha = 1;
    }

    get height(): number {
        return this.body.height;
    }
}

type Weapon = { sprite: AnimatedSprite; sheet: Spritesheet };

/** Null when the outfit has no art for this weapon (another class): the ninja fights bare-handed. */
async function loadWeapon(
    avatar: string,
    look: string,
): Promise<Weapon | null> {
    const url = weaponMotions(avatar, look);
    const found = await fetch(url, { method: 'HEAD' }).then(
        (response) => response.ok,
        () => false,
    );

    if (!found) {
        return null;
    }

    const sheet = await Assets.load<Spritesheet>(url);

    return {
        sheet,
        sprite: new AnimatedSprite({
            textures: sheet.animations.stance,
            updateAnchor: true,
        }),
    };
}

let vignetteTexture: Texture | null = null;

/** An alpha mask: opaque in the middle, clear towards every edge. */
function vignette(): Texture {
    if (vignetteTexture) {
        return vignetteTexture;
    }

    const size = 128;
    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;
    const context = canvas.getContext('2d');

    if (context) {
        const gradient = context.createRadialGradient(
            size / 2,
            size * 0.42,
            size * 0.3,
            size / 2,
            size * 0.42,
            size * 0.62,
        );
        gradient.addColorStop(0, 'rgba(255,255,255,1)');
        gradient.addColorStop(1, 'rgba(255,255,255,0)');
        context.fillStyle = gradient;
        context.fillRect(0, 0, size, size);
    }

    vignetteTexture = Texture.from(canvas);

    return vignetteTexture;
}

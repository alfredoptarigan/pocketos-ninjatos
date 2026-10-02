import {
    AnimatedSprite,
    Assets,
    Container,
    Graphics,
    Sprite,
    Text,
} from 'pixi.js';
import type { Spritesheet, Texture } from 'pixi.js';
import { characterAssets } from '@/types/game';
import type { FighterInfo } from './types';

// Pixi's ticker runs at 60 updates per second; SWF motions have their own fps.
const TICKER_FPS = 60;
const DEFAULT_FPS = 12;
const MOTION_SCALE = 1.7;
const PORTRAIT_SCALE = 1.15;
const HP_BAR_WIDTH = 130;
const HP_BAR_HEIGHT = 10;

export type Action = 'idle' | 'stance' | 'run' | 'attack' | 'dodge' | 'dead';

/** One fighter on the battle stage: its body, name and health bar. */
export class Fighter {
    readonly view = new Container();
    private readonly hpBar = new Graphics();
    private hp: number;

    private constructor(
        readonly info: FighterInfo,
        private readonly body: AnimatedSprite | Sprite,
        private readonly sheet: Spritesheet | null,
    ) {
        this.hp = info.hp;
        body.scale.set(sheet ? MOTION_SCALE : PORTRAIT_SCALE);
        // Original motions are drawn facing left; mirror opponents so they face the player.
        if (sheet && !info.avatar) {
            body.scale.x *= -1;
        }
        this.view.addChild(body, this.hpBar, this.nameLabel());
        this.drawHp();
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

    setHp(hp: number): void {
        this.hp = hp;
        this.drawHp();
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

    private nameLabel(): Text {
        const level = this.info.level ? ` Lv${this.info.level}` : '';
        const label = new Text({
            text: `${this.info.name}${level}`,
            style: {
                fontSize: 16,
                fill: this.info.isBoss ? 0xffd166 : 0xffffff,
                fontWeight: 'bold',
                stroke: { color: 0x000000, width: 4 },
            },
        });
        label.anchor.set(0.5, 1);
        label.y = -this.barTop() - 4;
        return label;
    }

    private barTop(): number {
        return this.body.height + 18;
    }

    private drawHp(): void {
        const share = Math.max(0, this.hp) / Math.max(1, this.info.maxHp);
        const y = -this.barTop() + 4;
        this.hpBar
            .clear()
            .roundRect(-HP_BAR_WIDTH / 2, y, HP_BAR_WIDTH, HP_BAR_HEIGHT, 4)
            .fill({ color: 0x000000, alpha: 0.6 })
            .roundRect(
                -HP_BAR_WIDTH / 2 + 1,
                y + 1,
                (HP_BAR_WIDTH - 2) * share,
                HP_BAR_HEIGHT - 2,
                3,
            )
            .fill(share > 0.3 ? 0x4ade80 : 0xef4444);
    }
}

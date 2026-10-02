import { Container, Graphics } from 'pixi.js';
import type { Ticker } from 'pixi.js';
import type { Action } from '@/game/battle/fighter';
import { ANIMATIONS, sample } from './animations';
import type { PartName, Pose } from './animations';
import { arm, head, KAZE, leg, scarf, scarfTail, torso } from './ninja-parts';
import type { Palette } from './ninja-parts';

// Rest positions of each joint (feet at 0,0; the upper body hangs off the hip).
const HIP = { x: 0, y: -30 };
const JOINTS: Record<Exclude<PartName, 'root'>, { x: number; y: number }> = {
    upper: HIP,
    legBack: { x: 5, y: -30 },
    legFront: { x: -4, y: -30 },
    armBack: { x: 8, y: -24 },
    armFront: { x: -8, y: -24 },
    head: { x: 0, y: -28 },
    scarfTail: { x: 4, y: -27 },
};
const HEIGHT = 105;
// Whole-body rotations (flips, falls) turn around the middle of the body, not the feet.
const BODY_CENTRE = 50;

const part = (svgSource: string) => new Graphics().svg(svgSource);

/**
 * An original vector ninja animated in code. Same interface as Fighter's
 * sprite side (play/flash/fadeOut/height), so it can stand in for sprites.
 */
export class VectorNinja {
    readonly view = new Container();
    readonly isAnimated = true;
    private readonly root = new Container();
    private readonly parts: Record<PartName, Container>;
    private current: {
        action: Action;
        speed: number;
        resolve?: () => void;
    } | null = null;
    private elapsed = 0;

    constructor(
        private readonly ticker: Ticker,
        palette: Palette = KAZE,
        scale = 1,
    ) {
        const upper = new Container();
        const make = (
            name: Exclude<PartName, 'root'>,
            graphic: Graphics,
            parent: Container,
        ) => {
            const holder = new Container();
            holder.addChild(graphic);
            holder.position.set(JOINTS[name].x, JOINTS[name].y);
            parent.addChild(holder);
            return holder;
        };

        // Back-to-front draw order.
        const legBack = make('legBack', part(leg(palette, true)), this.root);
        const legFront = make('legFront', part(leg(palette)), this.root);
        this.root.addChild(upper);
        upper.position.set(HIP.x, HIP.y);
        const armBack = make('armBack', part(arm(palette, true)), upper);
        const tail = make('scarfTail', part(scarfTail(palette)), upper);
        upper.addChild(part(torso(palette)));
        const neck = part(scarf(palette));
        neck.position.set(0, -28);
        upper.addChild(neck);
        const headPart = make('head', part(head(palette)), upper);
        const armFront = make('armFront', part(arm(palette)), upper);

        this.parts = {
            root: this.root,
            upper,
            legBack,
            legFront,
            armBack,
            armFront,
            head: headPart,
            scarfTail: tail,
        };
        this.root.scale.set(scale);
        this.root.pivot.set(0, -BODY_CENTRE);
        this.view.addChild(this.root);
        ticker.add(this.update);
    }

    get height(): number {
        return HEIGHT * this.root.scale.y;
    }

    play(action: Action, speed: number, loop = false): Promise<void> {
        this.current?.resolve?.();
        return new Promise((resolve) => {
            const animation = ANIMATIONS[action];
            this.elapsed = 0;
            this.current = {
                action,
                speed,
                resolve: animation.loop || loop ? undefined : resolve,
            };
            if (animation.loop || loop) {
                resolve();
            }
        });
    }

    flash(): void {
        this.root.tint = 0xff6b6b;
        setTimeout(() => (this.root.tint = 0xffffff), 140);
    }

    fadeOut(): void {
        this.root.alpha = 0.35;
    }

    destroy(): void {
        this.ticker.remove(this.update);
        this.view.destroy({ children: true });
    }

    // Arrow function: the ticker calls it unbound.
    private readonly update = (ticker: Ticker): void => {
        if (!this.current) {
            return;
        }

        const animation = ANIMATIONS[this.current.action];
        this.elapsed += ticker.deltaMS * this.current.speed;
        let progress = this.elapsed / animation.duration;

        if (progress >= 1) {
            if (animation.loop) {
                this.elapsed %= animation.duration;
                progress = this.elapsed / animation.duration;
            } else {
                progress = 1;
                this.apply(sample(animation, 1));
                // A full back-flip ends at 2π; normalise so later poses start upright.
                this.root.rotation %= Math.PI * 2;
                this.current.resolve?.();
                this.current = { ...this.current, resolve: undefined };
                return;
            }
        }

        this.apply(sample(animation, progress));
    };

    private apply(pose: Pose): void {
        for (const [name, holder] of Object.entries(this.parts) as [
            PartName,
            Container,
        ][]) {
            const offset = pose[name] ?? {};
            const rest =
                name === 'root'
                    ? { x: 0, y: -BODY_CENTRE * this.root.scale.y }
                    : JOINTS[name];
            holder.rotation = offset.r ?? 0;
            holder.position.set(
                rest.x + (offset.x ?? 0),
                rest.y + (offset.y ?? 0),
            );
        }
    }
}

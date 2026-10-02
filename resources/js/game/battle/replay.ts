import { Container, Text } from 'pixi.js';
import type { Ticker } from 'pixi.js';
import type { Fighter } from './fighter';
import { tween, wait } from './tween';
import type { BattleEvent, EndEvent, StrikeEvent } from './types';

export type ReplayContext = {
    ticker: Ticker;
    world: Container;
    fighters: [Fighter, Fighter];
    speed: () => number;
    isDisposed: () => boolean;
};

// Milliseconds at 1x speed.
const DASH_MS = 280;
const IMPACT_DELAY_MS = 220;
const RETURN_MS = 240;
const PAUSE_MS = 180;
const STRIKE_DISTANCE = 115;

/** Replay the server's battle log; resolves with the final event. */
export async function replay(
    ctx: ReplayContext,
    events: BattleEvent[],
): Promise<EndEvent | null> {
    for (const event of events) {
        if (ctx.isDisposed()) {
            return null;
        }

        if (event.type === 'end') {
            return event;
        }

        await strike(ctx, event);
    }

    return null;
}

async function strike(ctx: ReplayContext, event: StrikeEvent): Promise<void> {
    const attacker = ctx.fighters[event.actor];
    const target = ctx.fighters[event.target];
    const home = attacker.view.x;
    const direction = Math.sign(target.view.x - home);

    if (event.type === 'counter') {
        floatText(ctx, attacker, 'COUNTER!', 0x7dd3fc, 20);
    }

    void attacker.play('run', ctx.speed(), true);
    await tween(
        ctx.ticker,
        attacker.view,
        { x: target.view.x - direction * STRIKE_DISTANCE },
        DASH_MS,
        ctx.speed,
    );

    const swing = attacker.play('attack', ctx.speed());
    await wait(ctx.ticker, IMPACT_DELAY_MS, ctx.speed);
    impact(ctx, target, event);
    await swing;

    void attacker.play('run', ctx.speed(), true);
    await tween(ctx.ticker, attacker.view, { x: home }, RETURN_MS, ctx.speed);
    void attacker.play('stance', ctx.speed(), true);
    await wait(ctx.ticker, PAUSE_MS, ctx.speed);
}

function impact(ctx: ReplayContext, target: Fighter, event: StrikeEvent): void {
    if (!event.hit) {
        floatText(ctx, target, 'MISS', 0xe5e7eb, 22);
        void target
            .play('dodge', ctx.speed())
            .then(() => target.play('stance', ctx.speed(), true));
        return;
    }

    target.flash();
    target.setHp(event.targetHp);
    const label = event.crit
        ? `CRIT ${event.damage}`
        : event.parried
          ? `PARRY ${event.damage}`
          : `${event.damage}`;
    floatText(
        ctx,
        target,
        label,
        event.crit ? 0xfacc15 : event.parried ? 0x93c5fd : 0xffffff,
        event.crit ? 34 : 26,
    );

    if (event.targetHp === 0) {
        if (target.isAnimated) {
            void target.play('dead', ctx.speed());
        } else {
            target.fadeOut();
        }
    }
}

function floatText(
    ctx: ReplayContext,
    over: Fighter,
    text: string,
    color: number,
    size: number,
): void {
    const label = new Text({
        text,
        style: {
            fontSize: size,
            fontWeight: '900',
            fill: color,
            stroke: { color: 0x000000, width: 5 },
        },
    });
    label.anchor.set(0.5);
    label.position.set(over.view.x, over.view.y - over.height * 0.75);
    ctx.world.addChild(label);

    void tween(
        ctx.ticker,
        label,
        { y: label.y - 60, alpha: 0 },
        900,
        ctx.speed,
    ).then(() => label.destroy());
}

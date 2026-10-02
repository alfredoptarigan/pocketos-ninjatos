import { Container, Text } from 'pixi.js';
import type { Ticker } from 'pixi.js';
import type { Fighter } from './fighter';
import { tween, wait } from './tween';
import type { BattleEvent, EndEvent, StrikeEvent } from './types';

type Side = 0 | 1;

export type ReplayContext = {
    ticker: Ticker;
    world: Container;
    fighters: [Fighter, Fighter];
    speed: () => number;
    isDisposed: () => boolean;
    onHp: (side: Side, hp: number) => void;
    onMp: (side: Side, mp: number) => void;
    /** A jutsu was used (lets the HUD light up its icon). */
    onSkill: (side: Side, skillId: string) => void;
    skillName: (skillId: string) => string;
};

// Milliseconds at 1x speed.
const DASH_MS = 280;
const IMPACT_DELAY_MS = 220;
const RETURN_MS = 240;
const PAUSE_MS = 180;
const SHOUT_MS = 420;
const STRIKE_DISTANCE = 115;

const COLOURS = {
    damage: 0xffffff,
    crit: 0xfacc15,
    parry: 0x93c5fd,
    miss: 0xe5e7eb,
    jutsu: 0xfb923c,
    heal: 0x4ade80,
    status: 0xc4b5fd,
    counter: 0x7dd3fc,
};

/** Replay the server's battle log; resolves with the final event. */
export async function replay(
    ctx: ReplayContext,
    events: BattleEvent[],
): Promise<EndEvent | null> {
    for (const event of events) {
        if (ctx.isDisposed()) {
            return null;
        }

        switch (event.type) {
            case 'end':
                return event;
            case 'stunned':
                floatText(
                    ctx,
                    ctx.fighters[event.actor],
                    'STUNNED',
                    COLOURS.status,
                    22,
                );
                await wait(ctx.ticker, SHOUT_MS, ctx.speed);
                break;
            case 'reflect':
                shout(ctx, event.actor, event.skill);
                ctx.fighters[event.target].flash();
                ctx.onHp(event.target, event.targetHp);
                floatText(
                    ctx,
                    ctx.fighters[event.target],
                    `${event.damage}`,
                    COLOURS.damage,
                    26,
                );
                await wait(ctx.ticker, SHOUT_MS, ctx.speed);
                break;
            case 'heal':
                shout(ctx, event.actor, event.skill);
                ctx.onHp(event.actor, event.hp);
                floatText(
                    ctx,
                    ctx.fighters[event.actor],
                    `+${event.amount}`,
                    COLOURS.heal,
                    28,
                );
                await wait(ctx.ticker, SHOUT_MS, ctx.speed);
                break;
            case 'revive':
                shout(ctx, event.actor, event.skill);
                ctx.onHp(event.actor, event.hp);
                ctx.fighters[event.actor].restore();
                floatText(
                    ctx,
                    ctx.fighters[event.actor],
                    'REVIVED',
                    COLOURS.heal,
                    30,
                );
                await ctx.fighters[event.actor].play(
                    'stance',
                    ctx.speed(),
                    true,
                );
                await wait(ctx.ticker, SHOUT_MS, ctx.speed);
                break;
            default:
                await strike(ctx, event);
        }
    }

    return null;
}

/** Show a jutsu's name over its user and tell the HUD it was used. */
function shout(ctx: ReplayContext, side: Side, skillId: string): void {
    ctx.onSkill(side, skillId);
    floatText(
        ctx,
        ctx.fighters[side],
        `${ctx.skillName(skillId)}!`,
        COLOURS.jutsu,
        24,
        1.05,
    );
}

async function strike(ctx: ReplayContext, event: StrikeEvent): Promise<void> {
    const attacker = ctx.fighters[event.actor];
    // A thrown-back bomb is still thrown at the opponent before it comes back.
    const opponent = ctx.fighters[event.actor === 0 ? 1 : 0];
    const home = attacker.view.x;
    const direction = Math.sign(opponent.view.x - home);

    if (event.skill) {
        shout(ctx, event.actor, event.skill);
        ctx.onMp(event.actor, event.actorMp ?? 0);
    } else if (event.type === 'counter') {
        floatText(ctx, attacker, 'COUNTER!', COLOURS.counter, 20);
    }

    void attacker.play('run', ctx.speed(), true);
    await tween(
        ctx.ticker,
        attacker.view,
        { x: opponent.view.x - direction * STRIKE_DISTANCE },
        DASH_MS,
        ctx.speed,
    );

    const swing = attacker.play('attack', ctx.speed());
    await wait(ctx.ticker, IMPACT_DELAY_MS, ctx.speed);
    impact(ctx, event);
    await swing;

    void attacker.play('run', ctx.speed(), true);
    await tween(ctx.ticker, attacker.view, { x: home }, RETURN_MS, ctx.speed);
    void attacker.play('stance', ctx.speed(), true);
    await wait(ctx.ticker, PAUSE_MS, ctx.speed);
}

function impact(ctx: ReplayContext, event: StrikeEvent): void {
    const victim = ctx.fighters[event.target];

    if (event.actorHp !== undefined) {
        ctx.onHp(event.actor, event.actorHp);
    }

    if (event.backfire) {
        floatText(ctx, victim, 'THROWN BACK!', COLOURS.jutsu, 22, 1.05);
    }

    if (!event.hit) {
        floatText(ctx, victim, 'MISS', COLOURS.miss, 22);
        void victim
            .play('dodge', ctx.speed())
            .then(() => victim.play('stance', ctx.speed(), true));
        return;
    }

    if (event.blocked) {
        floatText(ctx, victim, 'BLOCKED', COLOURS.parry, 24);
        return;
    }

    victim.flash();
    ctx.onHp(event.target, event.targetHp);
    const label = event.crit
        ? `CRIT ${event.damage}`
        : event.parried
          ? `PARRY ${event.damage}`
          : `${event.damage}`;
    const colour = event.crit
        ? COLOURS.crit
        : event.parried
          ? COLOURS.parry
          : COLOURS.damage;
    floatText(ctx, victim, label, colour, event.crit ? 34 : 26);

    if (event.targetHp === 0) {
        if (victim.isAnimated) {
            void victim.play('dead', ctx.speed());
        } else {
            victim.fadeOut();
        }
    }
}

function floatText(
    ctx: ReplayContext,
    over: Fighter,
    text: string,
    color: number,
    size: number,
    heightShare = 0.75,
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
    label.position.set(over.view.x, over.view.y - over.height * heightShare);
    ctx.world.addChild(label);

    void tween(
        ctx.ticker,
        label,
        { y: label.y - 60, alpha: 0 },
        900,
        ctx.speed,
    ).then(() => label.destroy());
}

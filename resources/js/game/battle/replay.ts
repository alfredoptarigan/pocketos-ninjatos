import { Container, Text } from 'pixi.js';
import type { Ticker } from 'pixi.js';
import { playSfx } from '@/game/sfx';
import type { Sfx } from '@/game/sfx';
import { effectDuration, isRanged, playEffect } from './effects';
import type { EffectIndex, EffectSpec } from './effects';
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
    skillSound: (skillId: string) => Sfx;
    /** Original effects of the jutsu used in this battle. */
    effects: EffectIndex;
    /** Index in `world` where effects drawn under the fighters go. */
    underIndex: number;
};

// Milliseconds at 1x speed.
const DASH_MS = 280;
const IMPACT_DELAY_MS = 220;
const RETURN_MS = 240;
const PAUSE_MS = 180;
const SHOUT_MS = 420;
const STRIKE_DISTANCE = 115;
// A ranged jutsu lands this far into its effect.
const RANGED_IMPACT_SHARE = 0.7;

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
                playSfx(event.winner === 0 ? 'victory' : 'defeat');
                return event;
            case 'stunned':
                playSfx('stun');
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
                playSfx(ctx.skillSound(event.skill));
                void playAll(ctx, event.skill, event.actor);
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
                playSfx('heal');
                void playAll(ctx, event.skill, event.actor);
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
                playSfx('heal');
                void playAll(ctx, event.skill, event.actor);
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
    const specs = event.skill ? (ctx.effects[event.skill] ?? []) : [];
    // Ranged jutsu are cast from where the user stands, as in the original.
    const ranged = isRanged(specs);

    if (event.skill) {
        shout(ctx, event.actor, event.skill);
        ctx.onMp(event.actor, event.actorMp ?? 0);
        playSfx(ctx.skillSound(event.skill));
    } else if (event.type === 'counter') {
        floatText(ctx, attacker, 'COUNTER!', COLOURS.counter, 20);
    }

    if (!ranged) {
        void attacker.play('run', ctx.speed(), true);
        await tween(
            ctx.ticker,
            attacker.view,
            { x: opponent.view.x - direction * STRIKE_DISTANCE },
            DASH_MS,
            ctx.speed,
        );
    }

    const swing = attacker.play('attack', ctx.speed());
    const cast = Promise.all(
        specs
            .filter((spec) => spec.type === 'attack' && spec.start !== 'hit')
            .map((spec) =>
                playAt(
                    ctx,
                    spec,
                    event.actor,
                    event.target,
                    spec.start as number,
                ),
            ),
    );
    const lead = ranged
        ? Math.max(
              IMPACT_DELAY_MS,
              ...specs.map(
                  (spec) =>
                      (typeof spec.start === 'number' ? spec.start : 0) +
                      effectDuration(spec) * RANGED_IMPACT_SHARE,
              ),
          )
        : IMPACT_DELAY_MS;
    await wait(ctx.ticker, lead, ctx.speed);
    impact(ctx, event, specs);
    await Promise.all([swing, cast]);

    if (!ranged) {
        void attacker.play('run', ctx.speed(), true);
        await tween(
            ctx.ticker,
            attacker.view,
            { x: home },
            RETURN_MS,
            ctx.speed,
        );
    }

    void attacker.play('stance', ctx.speed(), true);
    await wait(ctx.ticker, PAUSE_MS, ctx.speed);
}

/**
 * Play an effect after `delay` ms: 'attack' effects at the jutsu user facing
 * their opponent, 'beaten' effects at `target` facing the user.
 */
async function playAt(
    ctx: ReplayContext,
    spec: EffectSpec,
    user: Side,
    target: Side,
    delay = 0,
): Promise<void> {
    if (delay > 0) {
        await wait(ctx.ticker, delay, ctx.speed);
    }

    const [self, other] = [
        ctx.fighters[user].view,
        ctx.fighters[user === 0 ? 1 : 0].view,
    ];
    const at = spec.type === 'attack' ? self : ctx.fighters[target].view;
    // A hit on the user themself (thrown-back bomb) still faces the opponent.
    const towards = spec.type === 'attack' || at === self ? other : self;
    await playEffect(ctx, spec, at.x, at.y, towards.x > at.x);
}

/** Every effect of a jutsu that does not strike (heal, revive, reflect, block). */
function playAll(
    ctx: ReplayContext,
    skillId: string,
    user: Side,
): Promise<unknown> {
    return Promise.all(
        (ctx.effects[skillId] ?? []).map((spec) =>
            // Defensive jutsu show their 'beaten' art on the user too (Substitution).
            playAt(
                ctx,
                spec,
                user,
                user,
                typeof spec.start === 'number' ? spec.start : 0,
            ),
        ),
    );
}

function impact(
    ctx: ReplayContext,
    event: StrikeEvent,
    specs: EffectSpec[],
): void {
    const victim = ctx.fighters[event.target];
    const landed = event.hit && !event.blocked;
    specs
        .filter(
            (spec) =>
                (spec.type === 'attack' && spec.start === 'hit') ||
                (spec.type === 'beaten' && landed),
        )
        .forEach((spec) => void playAt(ctx, spec, event.actor, event.target));

    if (event.actorHp !== undefined) {
        ctx.onHp(event.actor, event.actorHp);
    }

    if (event.backfire) {
        floatText(ctx, victim, 'THROWN BACK!', COLOURS.jutsu, 22, 1.05);
    }

    if (!event.hit) {
        floatText(ctx, victim, 'MISS', COLOURS.miss, 22);
        playSfx('miss');
        void victim
            .play('dodge', ctx.speed())
            .then(() => victim.play('stance', ctx.speed(), true));
        return;
    }

    if (event.blocked) {
        floatText(ctx, victim, 'BLOCKED', COLOURS.parry, 24);
        playSfx('block');

        if (event.blockSkill) {
            shout(ctx, event.target, event.blockSkill);
            void playAll(ctx, event.blockSkill, event.target);
        }

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
    playSfx(event.crit ? 'crit' : 'hit');

    if (event.targetHp === 0) {
        playSfx('ko');
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

import { Head } from '@inertiajs/react';
import type { Application, Container } from 'pixi.js';
import { useCallback, useRef } from 'react';
import type { RefObject } from 'react';
import { Fighter } from '@/game/battle/fighter';
import type { Action } from '@/game/battle/fighter';
import type { FighterInfo } from '@/game/battle/types';
import { VectorNinja } from '@/game/vector/vector-ninja';
import { usePixiApp } from '@/hooks/use-pixi-app';

const ACTIONS: Action[] = ['idle', 'stance', 'run', 'attack', 'dodge', 'dead'];
const LOOPING: Action[] = ['idle', 'stance', 'run'];
const SPRITE_SCALE = 1.7;
const GROUND_SHARE = 0.88;
// Original motions put their origin ~32px above the feet; the vector ninja's is at the feet.
const SPRITE_ORIGIN_ABOVE_FEET = 32 * SPRITE_SCALE;

// Only the avatar matters for drawing; the rest satisfies the battle type.
const ORIGINAL: FighterInfo = {
    name: 'Original (HD)',
    avatar: '1_26',
    hp: 1,
    maxHp: 1,
    minAttack: 0,
    maxAttack: 0,
    defense: 0,
    dodge: 0,
    crit: 0,
    parry: 0,
    counter: 0,
    priority: 0,
};

/** What both Fighter (sprites) and VectorNinja offer. */
type Performer = {
    view: Container;
    play: (action: Action, speed: number, loop?: boolean) => Promise<void>;
};

type StageProps = {
    title: string;
    note: string;
    build: (app: Application) => Promise<Performer>;
    performer: RefObject<Performer | null>;
    originAboveFeet?: number;
};

function Stage({
    title,
    note,
    build,
    performer,
    originAboveFeet = 0,
}: StageProps) {
    const hostRef = useRef<HTMLDivElement>(null);

    const setup = useCallback(
        async (app: Application, isDisposed: () => boolean) => {
            const actor = await build(app);

            if (isDisposed()) {
                return;
            }

            const place = () =>
                actor.view.position.set(
                    app.screen.width / 2,
                    app.screen.height * GROUND_SHARE - originAboveFeet,
                );
            place();
            app.renderer.on('resize', place);
            app.stage.addChild(actor.view);
            void actor.play('stance', 1, true);
            performer.current = actor;
        },
        [build, performer, originAboveFeet],
    );

    usePixiApp(hostRef, setup, { background: 0x2d4a3e });

    return (
        <section className="flex flex-col gap-2">
            <h2 className="font-semibold">{title}</h2>
            <div
                ref={hostRef}
                className="relative h-80 overflow-hidden rounded-lg border"
            />
            <p className="text-sm text-muted-foreground">{note}</p>
        </section>
    );
}

export default function CharacterLab() {
    const original = useRef<Performer | null>(null);
    const vector = useRef<Performer | null>(null);

    const buildOriginal = useCallback(() => Fighter.create(ORIGINAL), []);
    const buildVector = useCallback(
        async (app: Application) =>
            new VectorNinja(app.ticker, undefined, SPRITE_SCALE),
        [],
    );

    const perform = (action: Action) => {
        const loops = LOOPING.includes(action);

        for (const performer of [original.current, vector.current]) {
            if (!performer) {
                continue;
            }

            void performer.play(action, 1, loops).then(() => {
                if (!loops && action !== 'dead') {
                    void performer.play('stance', 1, true);
                }
            });
        }
    };

    return (
        <>
            <Head title="Character lab" />
            <div className="flex flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Character lab</h1>
                    <p className="text-muted-foreground">
                        Original art upscaled with Real-ESRGAN next to an
                        original vector ninja drawn and animated in code.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {ACTIONS.map((action) => (
                        <button
                            key={action}
                            type="button"
                            onClick={() => perform(action)}
                            className="game-button px-4 py-1 capitalize"
                        >
                            {action}
                        </button>
                    ))}
                </div>
                <div className="grid gap-4 md:grid-cols-2">
                    <Stage
                        title="Original sprite (HD)"
                        note="Avatar 1_26 from the backup, frames upscaled 2x (tools/upscale.py)."
                        build={buildOriginal}
                        originAboveFeet={SPRITE_ORIGIN_ABOVE_FEET}
                        performer={original}
                    />
                    <Stage
                        title="Vector remake: Kaze"
                        note="Original design, SVG parts rigged and animated by keyframes (resources/js/game/vector)."
                        build={buildVector}
                        performer={vector}
                    />
                </div>
            </div>
        </>
    );
}

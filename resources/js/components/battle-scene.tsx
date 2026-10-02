import { Assets, Container, Sprite } from 'pixi.js';
import type { Application, Texture } from 'pixi.js';
import { useCallback, useRef } from 'react';
import type { RefObject } from 'react';
import { Fighter } from '@/game/battle/fighter';
import { replay } from '@/game/battle/replay';
import type { BattleLog, EndEvent } from '@/game/battle/types';
import { usePixiApp } from '@/hooks/use-pixi-app';

// The original 500x300 battle backdrop, drawn at twice its size.
const WORLD_WIDTH = 1000;
const WORLD_HEIGHT = 600;
const GROUND_Y = 500;
// The player stands on the right facing left, as in the original client.
const PLAYER_X = 700;
const OPPONENT_X = 300;

type Props = {
    log: BattleLog;
    speed: RefObject<number>;
    onFinished: (end: EndEvent | null) => void;
};

export default function BattleScene({ log, speed, onFinished }: Props) {
    const hostRef = useRef<HTMLDivElement>(null);
    const onFinishedRef = useRef(onFinished);
    onFinishedRef.current = onFinished;

    const setup = useCallback(
        async (app: Application, isDisposed: () => boolean) => {
            const world = new Container();
            const background = new Sprite(
                await Assets.load<Texture>(
                    '/game-assets/battle/background.jpg',
                ),
            );
            background.setSize(WORLD_WIDTH, WORLD_HEIGHT);
            const fighters = (await Promise.all(
                log.fighters.map((info) => Fighter.create(info)),
            )) as [Fighter, Fighter];

            if (isDisposed()) {
                return;
            }

            fighters[0].view.position.set(PLAYER_X, GROUND_Y);
            fighters[1].view.position.set(OPPONENT_X, GROUND_Y);
            fighters.forEach((fighter) =>
                fighter.play('stance', speed.current, true),
            );
            world.addChild(background, fighters[1].view, fighters[0].view);
            app.stage.addChild(world);

            // Cover the screen with the scene, cropping the edges rather than letterboxing.
            const fit = () => {
                const scale = Math.max(
                    app.screen.width / WORLD_WIDTH,
                    app.screen.height / WORLD_HEIGHT,
                );
                world.scale.set(scale);
                world.position.set(
                    (app.screen.width - WORLD_WIDTH * scale) / 2,
                    (app.screen.height - WORLD_HEIGHT * scale) / 2,
                );
            };
            fit();
            app.renderer.on('resize', fit);

            const end = await replay(
                {
                    ticker: app.ticker,
                    world,
                    fighters,
                    speed: () => speed.current,
                    isDisposed,
                },
                log.events,
            );

            if (!isDisposed()) {
                onFinishedRef.current(end);
            }
        },
        [log, speed],
    );

    const error = usePixiApp(hostRef, setup, { background: 0x000000 });

    return (
        <div ref={hostRef} className="absolute inset-0">
            {error !== null && (
                <p className="absolute inset-x-4 top-4 rounded-md bg-red-950/90 p-3 font-mono text-sm text-red-100">
                    Battle assets are missing. Run: python3
                    tools/extract_tower_assets.py ~/Privates/game-pockieninja
                </p>
            )}
        </div>
    );
}

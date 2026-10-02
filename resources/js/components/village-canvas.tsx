import { Application, Container } from 'pixi.js';
import { useEffect, useRef } from 'react';
import { stepToward } from '@/game/movement';
import type { Point } from '@/game/movement';
import {
    createNinja,
    createTargetMarker,
    createVillage,
    NINJA_SPAWN,
    WORLD_HEIGHT,
    WORLD_WIDTH,
} from '@/game/village-scene';

// World pixels per frame at 60fps.
const NINJA_SPEED = 4;

type Props = { playerName: string };

export default function VillageCanvas({ playerName }: Props) {
    const hostRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const host = hostRef.current;

        if (!host) {
            return;
        }

        const app = new Application();
        // init is async; React may unmount (StrictMode) before it resolves.
        let disposed = false;
        let ready = false;

        app.init({ resizeTo: host, background: 0x0a0a0a, antialias: true })
            .then(() => {
                if (disposed) {
                    app.destroy(true, { children: true });
                    return;
                }

                ready = true;
                host.appendChild(app.canvas);
                mountScene(app, playerName);
            })
            .catch((error: unknown) => {
                console.error('Gagal memuat canvas desa', error);
            });

        return () => {
            disposed = true;

            if (ready) {
                app.destroy(true, { children: true });
            }
        };
    }, [playerName]);

    return <div ref={hostRef} className="absolute inset-0" />;
}

function mountScene(app: Application, playerName: string): void {
    const stage = new Container();
    const world = createVillage();
    const marker = createTargetMarker();
    const ninja = createNinja(playerName);

    let position: Point = NINJA_SPAWN;
    let target: Point = NINJA_SPAWN;
    ninja.position.set(position.x, position.y);

    world.on('pointertap', (event) => {
        const local = event.getLocalPosition(world);
        target = { x: local.x, y: local.y };
        marker.position.set(target.x, target.y);
        marker.visible = true;
    });

    stage.addChild(world, marker, ninja);
    app.stage.addChild(stage);

    app.ticker.add((ticker) => {
        // Letterbox the fixed-size world into the current canvas size.
        const scale = Math.min(
            app.screen.width / WORLD_WIDTH,
            app.screen.height / WORLD_HEIGHT,
        );
        stage.scale.set(scale);
        stage.position.set(
            (app.screen.width - WORLD_WIDTH * scale) / 2,
            (app.screen.height - WORLD_HEIGHT * scale) / 2,
        );

        position = stepToward(position, target, NINJA_SPEED * ticker.deltaTime);
        ninja.position.set(position.x, position.y);
        marker.visible = position.x !== target.x || position.y !== target.y;
    });
}

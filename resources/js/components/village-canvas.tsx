import type { Application, Container } from 'pixi.js';
import { useCallback, useEffect, useRef } from 'react';
import { usePixiApp } from '@/hooks/use-pixi-app';
import { createVillage, WORLD_HEIGHT, WORLD_WIDTH } from '@/game/village-scene';

type Props = {
    villageId: string;
    onBuildingSelect: (key: string) => void;
};

export default function VillageCanvas({ villageId, onBuildingSelect }: Props) {
    const hostRef = useRef<HTMLDivElement>(null);
    // Keep the latest callback without re-creating the Pixi app on every render.
    const onSelectRef = useRef(onBuildingSelect);

    useEffect(() => {
        onSelectRef.current = onBuildingSelect;
    }, [onBuildingSelect]);

    const setup = useCallback(
        async (app: Application, isDisposed: () => boolean) => {
            const world = await createVillage(villageId, (key) =>
                onSelectRef.current(key),
            );

            if (!isDisposed()) {
                mountWorld(app, world);
            }
        },
        [villageId],
    );

    const error = usePixiApp(hostRef, setup, { background: 0x0a0a0a });

    return (
        <>
            <div ref={hostRef} className="absolute inset-0" />
            {error !== null && (
                <p className="absolute inset-x-4 top-4 rounded-md bg-red-950/90 p-3 font-mono text-sm text-red-100">
                    Village assets are missing. Run: python3
                    tools/extract_village_assets.py ~/Privates/game-pockieninja
                </p>
            )}
        </>
    );
}

function mountWorld(app: Application, world: Container): void {
    app.stage.addChild(world);

    // Letterbox the fixed-size world into whatever size the canvas has.
    const fit = () => {
        const scale = Math.min(
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
}

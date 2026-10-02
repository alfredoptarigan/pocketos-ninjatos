import { Application } from 'pixi.js';
import type { Container } from 'pixi.js';
import { useEffect, useRef, useState } from 'react';
import { createVillage, WORLD_HEIGHT, WORLD_WIDTH } from '@/game/village-scene';

type Props = {
    villageId: string;
    onBuildingSelect: (key: string) => void;
};

export default function VillageCanvas({ villageId, onBuildingSelect }: Props) {
    const hostRef = useRef<HTMLDivElement>(null);
    // Keep the latest callback without re-creating the Pixi app on every render.
    const onSelectRef = useRef(onBuildingSelect);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        onSelectRef.current = onBuildingSelect;
    }, [onBuildingSelect]);

    useEffect(() => {
        const host = hostRef.current;

        if (!host) {
            return;
        }

        const app = new Application();
        // init is async; React may unmount (StrictMode) before it resolves.
        let disposed = false;
        let ready = false;

        const start = async () => {
            await app.init({
                resizeTo: host,
                background: 0x0a0a0a,
                antialias: true,
            });

            if (disposed) {
                app.destroy(true, { children: true });
                return;
            }

            ready = true;
            host.appendChild(app.canvas);
            const world = await createVillage(villageId, (key) =>
                onSelectRef.current(key),
            );

            if (!disposed) {
                mountWorld(app, world);
            }
        };

        start().catch((cause: unknown) => {
            console.error('Gagal memuat desa', cause);
            setError(
                'Aset desa belum ada. Jalankan: python3 tools/extract_village_assets.py ~/Privates/game-pockieninja',
            );
        });

        return () => {
            disposed = true;

            if (ready) {
                app.destroy(true, { children: true });
            }
        };
    }, [villageId]);

    return (
        <>
            <div ref={hostRef} className="absolute inset-0" />
            {error && (
                <p className="absolute inset-x-4 top-4 rounded-md bg-red-950/90 p-3 font-mono text-sm text-red-100">
                    {error}
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

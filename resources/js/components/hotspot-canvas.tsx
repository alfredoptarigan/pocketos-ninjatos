import type { Application, Container } from 'pixi.js';
import { useCallback, useEffect, useRef } from 'react';
import { usePixiApp } from '@/hooks/use-pixi-app';

type Props = {
    /** Changing it rebuilds the scene. */
    sceneKey: string;
    width: number;
    height: number;
    build: (
        onSelect: (key: string) => void,
        onHover: (key: string | null) => void,
    ) => Promise<Container>;
    onSelect: (key: string) => void;
    onHover?: (key: string | null) => void;
    /** Shown when the assets fail to load, e.g. the extractor to run. */
    missingHint: string;
};

/** A letterboxed Pixi scene of clickable spots (village buildings, world map areas). */
export default function HotspotCanvas({
    sceneKey,
    width,
    height,
    build,
    onSelect,
    onHover,
    missingHint,
}: Props) {
    const hostRef = useRef<HTMLDivElement>(null);
    // Keep the latest callbacks without re-creating the Pixi app on every render.
    const callbacks = useRef({ build, onSelect, onHover });

    useEffect(() => {
        callbacks.current = { build, onSelect, onHover };
    }, [build, onSelect, onHover]);

    const setup = useCallback(
        async (app: Application, isDisposed: () => boolean) => {
            const world = await callbacks.current.build(
                (key) => callbacks.current.onSelect(key),
                (key) => callbacks.current.onHover?.(key),
            );

            if (!isDisposed()) {
                app.stage.addChild(world);
                const fit = () => {
                    const scale = Math.min(
                        app.screen.width / width,
                        app.screen.height / height,
                    );
                    world.scale.set(scale);
                    world.position.set(
                        (app.screen.width - width * scale) / 2,
                        (app.screen.height - height * scale) / 2,
                    );
                };
                fit();
                app.renderer.on('resize', fit);
            }
        },
        [sceneKey, width, height],
    );

    const error = usePixiApp(hostRef, setup, { background: 0x0a0a0a });

    return (
        <>
            <div ref={hostRef} className="absolute inset-0" />
            {error !== null && (
                <p className="absolute inset-x-4 top-4 rounded-md bg-red-950/90 p-3 font-mono text-sm text-red-100">
                    {missingHint}
                </p>
            )}
        </>
    );
}

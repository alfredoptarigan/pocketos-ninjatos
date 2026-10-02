import { Application } from 'pixi.js';
import type { ApplicationOptions } from 'pixi.js';
import { useEffect, useRef, useState } from 'react';
import type { RefObject } from 'react';

type Setup = (app: Application, isDisposed: () => boolean) => Promise<void>;

/**
 * Mount a Pixi application into `hostRef` and run `setup` once it is ready.
 * Memoize `setup` (useCallback): a new function tears the app down and rebuilds it.
 */
export function usePixiApp(
    hostRef: RefObject<HTMLDivElement | null>,
    setup: Setup,
    options: Partial<ApplicationOptions> = {},
): unknown {
    const [error, setError] = useState<unknown>(null);
    // Options are read once per mount; they never change at runtime here.
    const optionsRef = useRef(options);

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
                antialias: true,
                ...optionsRef.current,
            });

            if (disposed) {
                app.destroy(true, { children: true });
                return;
            }

            ready = true;
            host.appendChild(app.canvas);
            await setup(app, () => disposed);
        };

        setError(null);
        start().catch((cause: unknown) => {
            console.error('Pixi setup failed', cause);
            setError(cause);
        });

        return () => {
            disposed = true;

            if (ready) {
                app.destroy(true, { children: true });
            }
        };
    }, [hostRef, setup]);

    return error;
}

import type { Ticker } from 'pixi.js';

const easeOutQuad = (t: number) => 1 - (1 - t) * (1 - t);

/**
 * Animate numeric properties of `target` over `ms` (scaled by `speed()`),
 * resolving when done. Uses the Pixi ticker so it pauses with the app.
 */
export function tween<T extends object>(
    ticker: Ticker,
    target: T,
    to: Partial<Record<keyof T, number>>,
    ms: number,
    speed: () => number,
): Promise<void> {
    const from = Object.fromEntries(
        Object.keys(to).map((key) => [key, target[key as keyof T] as number]),
    );
    let elapsed = 0;

    return new Promise((resolve) => {
        const step = (t: Ticker) => {
            elapsed += t.deltaMS * speed();
            const progress = Math.min(1, elapsed / ms);

            for (const [key, end] of Object.entries(to) as [
                keyof T,
                number,
            ][]) {
                const start = from[key as string];
                (target[key] as number) =
                    start + (end - start) * easeOutQuad(progress);
            }

            if (progress >= 1) {
                ticker.remove(step);
                resolve();
            }
        };
        ticker.add(step);
    });
}

export function wait(
    ticker: Ticker,
    ms: number,
    speed: () => number,
): Promise<void> {
    return tween(ticker, { t: 0 }, { t: 1 }, ms, speed);
}

export type Point = { readonly x: number; readonly y: number };

/** Move from `from` toward `to` by at most `maxDistance`, landing exactly on `to` when close enough. */
export function stepToward(from: Point, to: Point, maxDistance: number): Point {
    const dx = to.x - from.x;
    const dy = to.y - from.y;
    const distance = Math.hypot(dx, dy);

    if (distance <= maxDistance) {
        return { x: to.x, y: to.y };
    }

    const ratio = maxDistance / distance;

    return { x: from.x + dx * ratio, y: from.y + dy * ratio };
}

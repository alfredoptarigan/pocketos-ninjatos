import type { Action } from '@/game/battle/fighter';

export type PartName =
    | 'root'
    | 'upper'
    | 'legBack'
    | 'legFront'
    | 'armBack'
    | 'armFront'
    | 'head'
    | 'scarfTail';

/** Offsets from the rest pose: rotation in radians (positive swings limbs forward), x/y in pixels. */
export type Transform = { r?: number; x?: number; y?: number };
export type Pose = Partial<Record<PartName, Transform>>;
export type Animation = {
    duration: number;
    loop: boolean;
    keys: { at: number; pose: Pose }[];
};

const STANCE: Pose = {
    upper: { y: 2, r: -0.12 },
    legFront: { r: 0.35 },
    legBack: { r: -0.3 },
    armFront: { r: 1.0 },
    armBack: { r: -0.5 },
    head: { r: 0.08 },
};

const TAU = Math.PI * 2;

export const ANIMATIONS: Record<Action, Animation> = {
    idle: {
        duration: 1600,
        loop: true,
        keys: [
            { at: 0, pose: { armFront: { r: 0.1 }, armBack: { r: -0.1 } } },
            {
                at: 0.5,
                pose: {
                    upper: { y: 1.2 },
                    armFront: { r: 0.16 },
                    armBack: { r: -0.04 },
                    scarfTail: { r: 0.12 },
                },
            },
            { at: 1, pose: { armFront: { r: 0.1 }, armBack: { r: -0.1 } } },
        ],
    },
    stance: {
        duration: 900,
        loop: true,
        keys: [
            { at: 0, pose: STANCE },
            {
                at: 0.5,
                pose: {
                    ...STANCE,
                    upper: { y: 3.5, r: -0.12 },
                    scarfTail: { r: 0.15 },
                },
            },
            { at: 1, pose: STANCE },
        ],
    },
    run: {
        duration: 450,
        loop: true,
        keys: [
            {
                at: 0,
                pose: {
                    upper: { r: -0.3 },
                    legFront: { r: 0.8 },
                    legBack: { r: -0.8 },
                    armFront: { r: -0.9 },
                    armBack: { r: 0.9 },
                    scarfTail: { r: -0.2 },
                },
            },
            {
                at: 0.5,
                pose: {
                    upper: { r: -0.3, y: -2 },
                    legFront: { r: -0.8 },
                    legBack: { r: 0.8 },
                    armFront: { r: 0.9 },
                    armBack: { r: -0.9 },
                    scarfTail: { r: 0.2 },
                },
            },
            {
                at: 1,
                pose: {
                    upper: { r: -0.3 },
                    legFront: { r: 0.8 },
                    legBack: { r: -0.8 },
                    armFront: { r: -0.9 },
                    armBack: { r: 0.9 },
                    scarfTail: { r: -0.2 },
                },
            },
        ],
    },
    attack: {
        duration: 650,
        loop: false,
        keys: [
            { at: 0, pose: STANCE },
            {
                at: 0.25,
                pose: {
                    upper: { r: 0.15, y: 2 },
                    armFront: { r: -1.2 },
                    armBack: { r: 0.6 },
                    legFront: { r: 0.4 },
                    legBack: { r: -0.3 },
                },
            },
            {
                at: 0.45,
                pose: {
                    root: { x: -14 },
                    upper: { r: -0.45, y: 2 },
                    armFront: { r: 1.75 },
                    armBack: { r: -0.8 },
                    legFront: { r: 0.7 },
                    legBack: { r: -0.6 },
                    scarfTail: { r: 0.3 },
                },
            },
            {
                at: 0.7,
                pose: {
                    root: { x: -14 },
                    upper: { r: -0.45, y: 2 },
                    armFront: { r: 1.75 },
                    armBack: { r: -0.8 },
                    legFront: { r: 0.7 },
                    legBack: { r: -0.6 },
                },
            },
            { at: 1, pose: STANCE },
        ],
    },
    dodge: {
        duration: 600,
        loop: false,
        keys: [
            { at: 0, pose: STANCE },
            {
                at: 0.2,
                pose: {
                    upper: { y: 6 },
                    legFront: { r: 0.9 },
                    legBack: { r: -0.9 },
                    armFront: { r: 0.6 },
                },
            },
            {
                at: 0.6,
                pose: {
                    root: { x: 40, y: -45, r: TAU * 0.55 },
                    legFront: { r: 1.4 },
                    legBack: { r: 1.2 },
                    armFront: { r: 1.6 },
                    armBack: { r: 1.4 },
                },
            },
            { at: 1, pose: { ...STANCE, root: { r: TAU } } },
        ],
    },
    dead: {
        duration: 700,
        loop: false,
        keys: [
            { at: 0, pose: STANCE },
            {
                at: 0.4,
                pose: {
                    root: { r: 0.4, x: 10 },
                    head: { r: 0.3 },
                    armFront: { r: 1.5 },
                },
            },
            {
                at: 1,
                pose: {
                    // Lying on the back: the body centre ends ~22px above the ground.
                    root: { r: Math.PI / 2, x: 40, y: 28 },
                    head: { r: 0.3 },
                    armFront: { r: 2.5 },
                    armBack: { r: 2.8 },
                    legFront: { r: 0.2 },
                    legBack: { r: -0.1 },
                },
            },
        ],
    },
};

const ease = (t: number) => t * t * (3 - 2 * t); // smoothstep

/** The pose of an animation at `progress` (0..1), interpolated between its keys. */
export function sample(animation: Animation, progress: number): Pose {
    const keys = animation.keys;
    const next = keys.findIndex((key) => key.at >= progress);
    const to = keys[Math.max(0, next)];
    const from = keys[Math.max(0, next - 1)];
    const span = to.at - from.at;
    const t = span > 0 ? ease((progress - from.at) / span) : 1;
    const parts = new Set([
        ...Object.keys(from.pose),
        ...Object.keys(to.pose),
    ] as PartName[]);
    const pose: Pose = {};

    for (const part of parts) {
        const a = from.pose[part] ?? {};
        const b = to.pose[part] ?? {};
        pose[part] = {
            r: (a.r ?? 0) + ((b.r ?? 0) - (a.r ?? 0)) * t,
            x: (a.x ?? 0) + ((b.x ?? 0) - (a.x ?? 0)) * t,
            y: (a.y ?? 0) + ((b.y ?? 0) - (a.y ?? 0)) * t,
        };
    }

    return pose;
}

import { describe, expect, test } from 'vite-plus/test';
import { stepToward } from './movement';

describe('stepToward', () => {
    test('moves at most maxDistance toward the target', () => {
        const next = stepToward({ x: 0, y: 0 }, { x: 10, y: 0 }, 3);

        expect(next).toEqual({ x: 3, y: 0 });
    });

    test('snaps onto the target when it is within reach', () => {
        const next = stepToward({ x: 0, y: 0 }, { x: 3, y: 4 }, 10);

        expect(next).toEqual({ x: 3, y: 4 });
    });

    test('keeps direction on diagonal moves', () => {
        const next = stepToward({ x: 0, y: 0 }, { x: 30, y: 40 }, 5);

        expect(next).toEqual({ x: 3, y: 4 });
    });

    test('returns a new object instead of mutating the input', () => {
        const from = { x: 0, y: 0 };
        stepToward(from, { x: 10, y: 0 }, 3);

        expect(from).toEqual({ x: 0, y: 0 });
    });
});

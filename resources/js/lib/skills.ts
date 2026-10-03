import { router } from '@inertiajs/react';

// Props of the skill panel (App\Http\Controllers\SkillController::index).

export type Jutsu = {
    id: string;
    name: string;
    school: string;
    tier: number;
    kind: string;
    requires: string | null;
    description: string;
    chakra: number;
    /** Chance and power at the current level (level 1 if not learned). */
    chance: number;
    power: number;
    /** 0 = not learned, 1 = learned, 2.. = upgraded (+1..). */
    level: number;
    icon: string;
};

export type Passive = {
    id: string;
    school: string;
    name: string;
    level: number;
    icon: string;
};

export type SkillRules = {
    maxLevel: number;
    resetCoupons: number;
    passive: { levels: number[]; chance: number; power_percent: number };
    upgrade: { chance: number; power_percent: number };
};

export const KIND_LABELS: Record<string, string> = {
    strike: 'Used instead of an attack',
    follow_up: 'After an attack',
    extra: 'Before acting',
    prepare: 'Before acting',
    before_enemy: 'Before the opponent moves',
    counter: 'When attacked',
    block: 'When attacked',
    reflect: 'When hurt',
    heal: 'When hurt',
    hurt: 'When hurt',
    revive: 'On knock-out',
    battle: 'All fight long',
};

/** "+3" for an upgraded jutsu, "" otherwise. */
export function upgradeLabel(level: number): string {
    return level > 1 ? `+${level - 1}` : '';
}

/** POST to a skill route, keeping the scroll position. */
export function send(url: string, data: Record<string, unknown> = {}): void {
    router.post(url, data as Record<string, string | number>, {
        preserveScroll: true,
    });
}

/** dataTransfer type for dragging a jutsu onto an equipped slot. */
export const DRAG_TYPE = 'application/x-jutsu';

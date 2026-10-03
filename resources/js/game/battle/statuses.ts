import type {
    ActiveStatus,
    CountdownEvent,
    ExpireEvent,
    StatusEvent,
} from './types';

export type StatusChange = StatusEvent | ExpireEvent | CountdownEvent;

// What floats over a fighter when a status lands (BattleSimulator statuses).
export const STATUS_LABELS: Record<string, string> = {
    burn: 'BURNING',
    drunk: 'DRUNK',
    freeze: 'FROZEN',
    slow: 'SLOWED',
    shield: 'SHIELD',
    invulnerable: 'UNTOUCHABLE',
    poison: 'POISONED',
    seal: 'SEALED',
    dead_demon: 'DEMON SEAL',
    bloodboil: 'BLOODBOIL',
    charm: 'CHARMED',
    snare: 'SNARED',
    clay: 'CLAY',
    prison: 'DEFENSE DOWN',
    mirage: 'NIGHTMARE',
    regen: 'REGENERATING',
    chakra_burn: 'CHAKRA DRAIN',
    gates: 'GATE OPEN',
    cloud: 'THUNDER CLOUD',
    mist: 'MIST',
    sunset: 'SUNSET',
    cursed_seal: 'CURSED SEAL',
};

export function statusLabel(status: string): string {
    return STATUS_LABELS[status] ?? status.replace('_', ' ').toUpperCase();
}

/** One side's statuses after a status, expire or countdown event. */
export function applyStatusChange(
    statuses: ActiveStatus[],
    change: StatusChange,
): ActiveStatus[] {
    switch (change.type) {
        case 'status':
            return [
                ...statuses.filter(({ status }) => status !== change.status),
                {
                    status: change.status,
                    skill: change.skill,
                    turns: change.turns,
                    source: change.source,
                },
            ];
        case 'expire':
            return statuses.filter(({ status }) => status !== change.status);
        case 'countdown':
            return statuses.map((active) =>
                active.turns === null
                    ? active
                    : { ...active, turns: active.turns - 1 },
            );
    }
}

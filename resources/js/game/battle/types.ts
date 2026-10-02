// Mirrors the log written by App\Actions\ChallengeTowerFloor.

type Backdrop = { background?: string };

export type MonsterArt =
    | ({ type: 'motion'; motions: string; face: string } & Backdrop)
    | ({ type: 'portrait'; portrait: string; face: string } & Backdrop);

export type FighterInfo = {
    name: string;
    hp: number;
    maxHp: number;
    minAttack: number;
    maxAttack: number;
    defense: number;
    dodge: number;
    crit: number;
    parry: number;
    counter: number;
    priority: number;
    // Older battles were recorded before chakra was logged.
    mp?: number;
    maxMp?: number;
    avatar?: string;
    level?: number;
    isBoss?: boolean;
    art?: MonsterArt;
};

export type StrikeEvent = {
    type: 'attack' | 'counter';
    actor: 0 | 1;
    target: 0 | 1;
    hit: boolean;
    crit: boolean;
    parried: boolean;
    damage: number;
    targetHp: number;
};

export type EndEvent = { type: 'end'; winner: 0 | 1; reason: 'ko' | 'timeout' };

export type BattleEvent = StrikeEvent | EndEvent;

export type BattleLog = {
    fighters: [FighterInfo, FighterInfo];
    events: BattleEvent[];
};

export type BattleRewards = {
    exp: number;
    gold: number;
    levelUp: boolean;
    firstClear: boolean;
};

export type BattleRecord = {
    id: number;
    floor: number;
    won: boolean;
    log: BattleLog;
    rewards: BattleRewards;
};

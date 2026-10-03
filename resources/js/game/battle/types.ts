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
    // Learned jutsu ids; missing on battles recorded before jutsu existed.
    skills?: string[];
    isBoss?: boolean;
    art?: MonsterArt;
};

type Side = 0 | 1;

export type StrikeEvent = {
    type: 'attack' | 'counter' | 'follow_up' | 'extra';
    actor: Side;
    target: Side;
    hit: boolean;
    crit: boolean;
    parried: boolean;
    blocked?: boolean;
    // The jutsu that blocked it (Substitution).
    blockSkill?: string;
    damage: number;
    targetHp: number;
    // Present when a jutsu was used.
    skill?: string;
    mpCost?: number;
    actorMp?: number;
    actorHp?: number;
    backfire?: boolean;
};

export type StunnedEvent = { type: 'stunned'; actor: Side };
export type ReflectEvent = {
    type: 'reflect';
    actor: Side;
    target: Side;
    skill: string;
    damage: number;
    targetHp: number;
};
export type HealEvent = {
    type: 'heal';
    actor: Side;
    skill: string;
    amount: number;
    hp: number;
};
export type ReviveEvent = {
    type: 'revive';
    actor: Side;
    skill: string;
    hp: number;
};

export type EndEvent = { type: 'end'; winner: 0 | 1; reason: 'ko' | 'timeout' };

export type BattleEvent =
    | StrikeEvent
    | StunnedEvent
    | ReflectEvent
    | HealEvent
    | ReviveEvent
    | EndEvent;

export type SkillInfo = { name: string; icon: string; school: string };

export type BattleLog = {
    fighters: [FighterInfo, FighterInfo];
    events: BattleEvent[];
};

export type GearDrop = { name: string; icon: string; level: number };

export type BattleRewards = {
    exp: number;
    gold: number;
    levelUp: boolean;
    firstClear: boolean;
    // Missing on battles recorded before gear existed.
    drop?: GearDrop | null;
    // Dungeon waves and tower first clears.
    coupons?: number;
    // New tower floors and dungeon clears: honor, and as many medals.
    honor?: number;
    // Dungeon waves only: the stage this win finished, and whether it was the last one.
    stageCleared?: string | null;
    dungeonCleared?: boolean;
};

export type BattleRecord = {
    id: number;
    // Tower battles have a floor; hunting-ground battles have a field.
    floor: number | null;
    field: { scene: string; name: string; monster: number } | null;
    // Dungeon waves; `active` is false once the run is cleared, lost or left.
    dungeon: { id: number; name: string; run: number; active: boolean } | null;
    won: boolean;
    log: BattleLog;
    rewards: BattleRewards;
};

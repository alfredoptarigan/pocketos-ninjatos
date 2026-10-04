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
    // The look of the weapon in hand, e.g. "sharp20"; null when unarmed.
    weapon?: string | null;
    level?: number;
    // Learned jutsu ids; missing on battles recorded before jutsu existed.
    skills?: string[];
    // The outfit's ultimate jutsu id; missing on older battles and most monsters.
    ultimate?: string | null;
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
    // Status notes (BattleSimulator): untouchable, lost in mist, Sunset's
    // double damage, a frozen target shattered, damage soaked by Static Field.
    immune?: boolean;
    mist?: boolean;
    double?: boolean;
    shatter?: boolean;
    absorbed?: number;
    // Chakra drained or cut from the target.
    targetMp?: number;
};

export type StunnedEvent = {
    type: 'stunned';
    actor: Side;
    // Missing for a plain stun.
    reason?: 'freeze' | 'charm' | 'slow';
};
// A jutsu with only an effect (Crystal Blade, Earth Prison, ...).
export type CastEvent = {
    type: 'cast';
    actor: Side;
    target: Side;
    skill: string;
    mpCost: number;
    actorMp: number;
    targetMp?: number;
};
export type StatusEvent = {
    type: 'status';
    actor: Side;
    status: string;
    turns: number | null;
    skill: string | null;
    source: Side;
};
export type ExpireEvent = {
    type: 'expire';
    actor: Side;
    status: string;
    // The jutsu that lifted it, if any.
    skill?: string;
};
// A side finished its turn: its timed statuses lose one turn.
export type CountdownEvent = { type: 'countdown'; actor: Side };
// Burn, poison, nightmares, healing over time and exploding clay.
export type TickEvent = {
    type: 'tick';
    actor: Side;
    status: string;
    hp: number;
    damage?: number;
    heal?: number;
    mp?: number;
};
export type CloudEvent = {
    type: 'cloud';
    actor: Side;
    target: Side;
    damage: number;
    absorbed: number;
    targetHp: number;
};
export type HasteEvent = { type: 'haste'; actor: Side };
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

/** An outfit's ultimate finishing a low opponent (App\Game\Ultimate). */
export type UltimateEvent = {
    type: 'ultimate';
    actor: Side;
    target: Side;
    skill: string;
    // Outfit at +19 or more: the stronger cinematic (effect `${skill}0`).
    upgraded: boolean;
    damage: number;
    targetHp: number;
};

export type EndEvent = { type: 'end'; winner: 0 | 1; reason: 'ko' | 'timeout' };

export type BattleEvent =
    | StrikeEvent
    | StunnedEvent
    | CastEvent
    | StatusEvent
    | CountdownEvent
    | ExpireEvent
    | TickEvent
    | CloudEvent
    | HasteEvent
    | ReflectEvent
    | HealEvent
    | ReviveEvent
    | UltimateEvent
    | EndEvent;

export type SkillInfo = {
    name: string;
    icon: string;
    school: string;
    description: string;
};

/** A status on a fighter, as the battle HUD shows it. */
export type ActiveStatus = {
    status: string;
    skill: string | null;
    /** Turns left; null for the rest of the fight. */
    turns: number | null;
    source: Side;
};

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

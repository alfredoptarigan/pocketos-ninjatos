export type Character = {
    name: string;
    /** Asset key "<sex>_<id>" the ninja is drawn with: the worn outfit, else the created avatar. */
    avatar: string;
    level: number;
    exp: number;
    exp_to_next: number;
    hp: number;
    max_hp: number;
    mp: number;
    max_mp: number;
    gold: number;
    /** Gift coupons, spent at the Wishing Pot. */
    coupons: number;
    /** Key of config('game.villages'), e.g. "111". */
    village: string;
};

export type Rarity = 'grey' | 'blue' | 'orange';

export type Outfit = {
    id: number;
    key: string;
    name: string;
    rarity: Rarity;
    /** Percent added to health, attack and defense while worn. */
    bonus: number;
};

/** Text colours of the original item grades. */
export const RARITY_TEXT: Record<Rarity, string> = {
    grey: 'text-slate-300',
    blue: 'text-sky-400',
    orange: 'text-orange-400',
};

export type Difficulty = 'trial' | 'normal' | 'hard';

/** Badge colours of dungeon difficulties. */
export const DIFFICULTY_STYLE: Record<Difficulty, string> = {
    trial: 'bg-slate-600 text-slate-100',
    normal: 'bg-emerald-700 text-emerald-50',
    hard: 'bg-red-700 text-red-50',
};

export const RARITY_BORDER: Record<Rarity, string> = {
    grey: 'border-slate-500',
    blue: 'border-sky-500',
    orange: 'border-orange-500 shadow-[0_0_10px_rgba(251,146,60,0.6)]',
};

export type ShopItem = {
    id: number;
    name: string;
    icon: string;
    price: number;
    restore_hp: number;
    restore_chakra: number;
    restore_energy: number;
    max_stack: number;
};

const CHARACTER_ASSETS = '/game-assets/characters';

export function characterAssets(avatar: string) {
    const base = `${CHARACTER_ASSETS}/${avatar}`;

    return {
        portrait: `${base}/portrait.png`,
        face: `${base}/face.png`,
        motions: `${base}/motions.json`,
    };
}

export function isFemaleAvatar(avatar: string): boolean {
    return avatar.startsWith('1_');
}

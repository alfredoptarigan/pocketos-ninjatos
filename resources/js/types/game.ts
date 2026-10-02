export type Character = {
    name: string;
    /** Asset key "<sex>_<id>" from config('game.avatars'). */
    avatar: string;
    level: number;
    exp: number;
    exp_to_next: number;
    hp: number;
    max_hp: number;
    mp: number;
    max_mp: number;
    gold: number;
    /** Key of config('game.villages'), e.g. "111". */
    village: string;
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

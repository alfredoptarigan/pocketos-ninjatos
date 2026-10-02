export type Character = {
    name: string;
    /** Asset key "<sex>_<id>" from config('game.avatars'). */
    avatar: string;
    level: number;
    gold: number;
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
        idle: `${base}/idle.json`,
    };
}

export function isFemaleAvatar(avatar: string): boolean {
    return avatar.startsWith('1_');
}

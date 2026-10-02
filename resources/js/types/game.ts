export type Character = {
    name: string;
    /** Asset key "<sex>_<id>" from config('game.avatars'). */
    avatar: string;
    level: number;
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

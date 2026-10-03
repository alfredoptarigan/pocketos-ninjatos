// Sound effects (Kenney, CC0: public/sfx/LICENSE-kenney.txt). The original
// client shipped no sound effects, only music.

const MUTED_KEY = 'pocketo:music-muted';
const VOLUME = 0.6;

export type Sfx =
    | 'hit'
    | 'crit'
    | 'miss'
    | 'block'
    | 'stun'
    | 'ko'
    | 'heal'
    | 'victory'
    | 'defeat'
    | 'fire'
    | 'lightning'
    | 'wind'
    | 'explosion'
    | 'taijutsu'
    | 'genjutsu'
    | 'earth'
    | 'tool';

// config('skills.skills.*.school') -> cast sound.
const SCHOOL_SOUNDS: Record<string, Sfx> = {
    fire: 'fire',
    water: 'genjutsu',
    earth: 'earth',
    lightning: 'lightning',
    wind: 'wind',
    body: 'taijutsu',
    tools: 'explosion',
    seal: 'genjutsu',
    illusion: 'genjutsu',
    healing: 'heal',
};

const loaded = new Map<Sfx, HTMLAudioElement>();

/** One mute switch (the music button) silences music and sound effects. */
export function isMuted(): boolean {
    try {
        return localStorage.getItem(MUTED_KEY) === '1';
    } catch {
        return false;
    }
}

export function setMuted(muted: boolean): void {
    try {
        localStorage.setItem(MUTED_KEY, muted ? '1' : '0');
    } catch {
        // Storage can be unavailable (private mode); muting still works for this visit.
    }
}

export function schoolSound(school: string | undefined): Sfx {
    return (school && SCHOOL_SOUNDS[school]) || 'tool';
}

export function playSfx(name: Sfx): void {
    if (isMuted()) {
        return;
    }

    const source = loaded.get(name) ?? new Audio(`/sfx/${name}.m4a`);
    loaded.set(name, source);
    // A clone lets the same sound overlap itself (quick hits).
    const sound = source.cloneNode() as HTMLAudioElement;
    sound.volume = VOLUME;
    sound.play().catch(() => undefined);
}

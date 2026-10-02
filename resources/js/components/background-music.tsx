import { Volume2, VolumeX } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const MUTED_KEY = 'pocketo:music-muted';
const VOLUME = 0.35;

function readMuted(): boolean {
    try {
        return localStorage.getItem(MUTED_KEY) === '1';
    } catch {
        return false;
    }
}

function writeMuted(muted: boolean): void {
    try {
        localStorage.setItem(MUTED_KEY, muted ? '1' : '0');
    } catch {
        // Storage can be unavailable (private mode); muting still works for this visit.
    }
}

/**
 * Loops `src` in the background with a mute toggle. Lives in the persistent
 * game layout, so music keeps playing across page visits.
 */
export default function BackgroundMusic({ src }: { src: string }) {
    const audioRef = useRef<HTMLAudioElement | null>(null);
    const [muted, setMuted] = useState(readMuted);

    useEffect(() => {
        const audio = audioRef.current ?? new Audio();
        audioRef.current = audio;
        audio.loop = true;
        audio.volume = VOLUME;

        if (!audio.src.endsWith(src)) {
            audio.src = src;
        }

        if (muted) {
            audio.pause();
            return;
        }

        // Browsers block autoplay until the first user gesture; retry then.
        const start = () => {
            audio.play().catch(() => undefined);
        };
        audio
            .play()
            .catch(() =>
                window.addEventListener('pointerdown', start, { once: true }),
            );

        return () => window.removeEventListener('pointerdown', start);
    }, [src, muted]);

    useEffect(() => () => audioRef.current?.pause(), []);

    const toggle = () => {
        setMuted((current) => {
            writeMuted(!current);
            return !current;
        });
    };

    return (
        <button
            type="button"
            onClick={toggle}
            aria-label={muted ? 'Play music' : 'Mute music'}
            aria-pressed={!muted}
            className="game-button grid size-9 place-items-center"
        >
            {muted ? (
                <VolumeX className="size-4" />
            ) : (
                <Volume2 className="size-4" />
            )}
        </button>
    );
}

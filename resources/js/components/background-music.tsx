import { Volume2, VolumeX } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { isMuted, setMuted as storeMuted } from '@/game/sfx';

const VOLUME = 0.35;

/**
 * Loops `src` in the background with a mute toggle. Lives in the persistent
 * game layout, so music keeps playing across page visits.
 */
export default function BackgroundMusic({ src }: { src: string }) {
    const audioRef = useRef<HTMLAudioElement | null>(null);
    const [muted, setMuted] = useState(isMuted);

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
            storeMuted(!current);
            return !current;
        });
    };

    return (
        <button
            type="button"
            onClick={toggle}
            aria-label={muted ? 'Play sound' : 'Mute sound'}
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

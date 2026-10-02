import { characterAssets } from '@/types/game';
import type { Character } from '@/types/game';

/** Face, name, level and gold badge for the game HUD. */
export default function PlayerHud({ character }: { character: Character }) {
    return (
        <div className="flex items-center gap-3 rounded-full bg-black/60 py-1 pr-5 pl-1 text-white shadow-lg backdrop-blur">
            <img
                src={characterAssets(character.avatar).face}
                alt=""
                className="size-12 rounded-full border-2 border-amber-400 bg-slate-700 object-cover"
            />
            <div className="leading-tight">
                <p className="font-semibold">{character.name}</p>
                <p className="text-xs text-amber-300">
                    Level {character.level} ·{' '}
                    {character.gold.toLocaleString('en-US')} gold
                </p>
            </div>
        </div>
    );
}

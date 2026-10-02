import { characterAssets } from '@/types/game';
import type { Character } from '@/types/game';

/** Face, name and level badge, drawn over the top-left of a game screen. */
export default function PlayerHud({ character }: { character: Character }) {
    return (
        <div className="absolute top-3 left-3 z-10 flex items-center gap-3 rounded-full bg-black/60 py-1 pr-5 pl-1 text-white shadow-lg backdrop-blur">
            <img
                src={characterAssets(character.avatar).face}
                alt=""
                className="size-12 rounded-full border-2 border-amber-400 bg-slate-700 object-cover"
            />
            <div className="leading-tight">
                <p className="font-semibold">{character.name}</p>
                <p className="text-xs text-amber-300">
                    Level {character.level} ·{' '}
                    {character.gold.toLocaleString('id-ID')} koin
                </p>
            </div>
        </div>
    );
}

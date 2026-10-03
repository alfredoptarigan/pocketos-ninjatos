import StatBar from '@/components/stat-bar';
import { characterAssets } from '@/types/game';
import type { Character } from '@/types/game';

/** Face, name, level, gold and health/chakra/experience bars for the game HUD. */
export default function PlayerHud({ character }: { character: Character }) {
    return (
        <div className="flex items-center gap-3 rounded-2xl bg-black/60 py-1.5 pr-4 pl-1.5 text-white shadow-lg backdrop-blur">
            <img
                src={characterAssets(character.avatar).face}
                alt=""
                className="size-14 rounded-full border-2 border-amber-400 bg-slate-700 object-cover"
            />
            <div className="flex w-48 flex-col gap-1 leading-tight">
                <p className="flex justify-between font-semibold">
                    <span>{character.name}</span>
                    <span className="text-xs text-amber-300">
                        Lv {character.level}
                    </span>
                </p>
                <StatBar
                    label="HP"
                    value={character.hp}
                    max={character.max_hp}
                    color="bg-red-500"
                />
                <StatBar
                    label="CP"
                    value={character.mp}
                    max={character.max_mp}
                    color="bg-sky-500"
                />
                <StatBar
                    label="EXP"
                    value={character.exp}
                    max={character.exp_to_next}
                    color="bg-emerald-500"
                    className="h-2.5"
                />
                <p className="flex justify-between text-xs">
                    <span className="text-amber-300">
                        {character.gold.toLocaleString('en-US')} gold
                    </span>
                    <span className="text-rose-300">
                        {character.coupons.toLocaleString('en-US')} coupons
                    </span>
                </p>
            </div>
        </div>
    );
}

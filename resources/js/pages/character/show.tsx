import { Head, Link, router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import CharacterSprite from '@/components/character-sprite';
import GameWindow from '@/components/game-window';
import VillageBackdrop from '@/components/village-backdrop';
import { bonuses, SLOT_LABELS } from '@/lib/gear';
import { cn } from '@/lib/utils';
import { village } from '@/routes';
import { equip, unequip } from '@/routes/character/gear';
import { index as wardrobe } from '@/routes/outfits';
import { characterAssets } from '@/types/game';

type Piece = {
    id: number;
    code: string;
    name: string;
    slot: string;
    level: number;
    icon: string;
    min_attack: number;
    max_attack: number;
    defense: number;
    max_hp: number;
    crit: number;
};

type Stats = {
    maxHp: number;
    maxMp: number;
    minAttack: number;
    maxAttack: number;
    defense: number;
    dodge: number;
    crit: number;
    parry: number;
};

type Props = {
    stats: Stats;
    slots: string[];
    worn: Record<string, Piece>;
    bag: Piece[];
    /** False while an outfit is worn: outfits have no create-screen portrait. */
    hasPortrait: boolean;
};

function send(url: string) {
    router.post(
        url,
        {},
        {
            preserveScroll: true,
            onError: (errors) =>
                toast.error(errors.gear ?? 'That did not work.'),
        },
    );
}

export default function CharacterPanel({
    stats,
    slots,
    worn,
    bag,
    hasPortrait,
}: Props) {
    const { character } = usePage().props;

    if (!character) {
        return null;
    }

    // The original panel puts four slots on each side of the ninja.
    const half = Math.ceil(slots.length / 2);
    const column = (list: string[]) => (
        <ul className="flex flex-col gap-2">
            {list.map((slot) => (
                <li key={slot}>
                    <Slot slot={slot} piece={worn[slot]} />
                </li>
            ))}
        </ul>
    );
    const rows: [string, string][] = [
        ['Health', `${stats.maxHp}`],
        ['Chakra', `${stats.maxMp}`],
        ['Attack', `${stats.minAttack} - ${stats.maxAttack}`],
        ['Defense', `${stats.defense}`],
        ['Dodge', `${stats.dodge}%`],
        ['Critical', `${stats.crit}%`],
        ['Block', `${stats.parry}%`],
    ];

    return (
        <>
            <Head title="Character" />
            <VillageBackdrop>
                <GameWindow title="Character" closeHref={village().url}>
                    <div className="grid gap-4 md:grid-cols-[auto_1fr]">
                        <div className="flex flex-col gap-3">
                            <div className="flex items-center gap-2">
                                {column(slots.slice(0, half))}
                                <div className="flex w-[150px] flex-col items-center">
                                    {hasPortrait ? (
                                        <img
                                            src={
                                                characterAssets(
                                                    character.avatar,
                                                ).portrait
                                            }
                                            alt={character.name}
                                            className="h-[220px] w-auto object-contain drop-shadow-lg"
                                        />
                                    ) : (
                                        <CharacterSprite
                                            key={character.avatar}
                                            avatar={character.avatar}
                                            className="h-[220px] w-[150px]"
                                        />
                                    )}
                                    <p className="font-semibold text-amber-200">
                                        {character.name}
                                    </p>
                                    <p className="text-sm text-lime-400">
                                        Level {character.level}
                                    </p>
                                    <Link
                                        href={wardrobe()}
                                        className="game-button mt-1 px-3 py-0.5 text-sm"
                                    >
                                        Wardrobe
                                    </Link>
                                </div>
                                {column(slots.slice(half))}
                            </div>
                            <dl
                                aria-label="Battle stats"
                                className="grid grid-cols-2 gap-x-6 rounded-md border border-amber-900/70 bg-slate-950/60 p-2 text-sm"
                            >
                                {rows.map(([label, value]) => (
                                    <div
                                        key={label}
                                        className="flex justify-between"
                                    >
                                        <dt className="text-slate-400">
                                            {label}
                                        </dt>
                                        <dd>{value}</dd>
                                    </div>
                                ))}
                            </dl>
                        </div>

                        <section aria-label="Gear in the bag">
                            <h2 className="mb-2 font-semibold text-amber-200">
                                Gear in the bag
                            </h2>
                            {bag.length === 0 ? (
                                <p className="text-sm text-slate-400">
                                    No spare gear yet. Clear Training Tower
                                    floors to find some.
                                </p>
                            ) : (
                                <ul className="grid max-h-[55vh] gap-2 overflow-y-auto pr-1">
                                    {bag.map((piece) => (
                                        <BagPiece
                                            key={piece.id}
                                            piece={piece}
                                            level={character.level}
                                        />
                                    ))}
                                </ul>
                            )}
                        </section>
                    </div>
                </GameWindow>
            </VillageBackdrop>
        </>
    );
}

function Slot({ slot, piece }: { slot: string; piece?: Piece }) {
    const label = SLOT_LABELS[slot] ?? slot;

    if (!piece) {
        return (
            <div
                title={`${label}: empty`}
                className="grid size-14 place-items-center rounded-md border border-dashed border-amber-900/80 bg-slate-950/60 text-[10px] text-slate-500"
            >
                {label}
            </div>
        );
    }

    return (
        <button
            type="button"
            onClick={() => send(unequip(piece.id).url)}
            title={`${piece.name} (Lv ${piece.level}): ${bonuses(piece)}. Click to take off.`}
            aria-label={`Take off ${piece.name}`}
            className="size-14 rounded-md border border-amber-500/80 bg-slate-950/60 p-1 transition hover:border-amber-300"
        >
            <img src={piece.icon} alt="" className="size-full" />
        </button>
    );
}

function BagPiece({ piece, level }: { piece: Piece; level: number }) {
    const tooLow = level < piece.level;

    return (
        <li className="flex items-center gap-3 rounded-md border border-amber-900/70 bg-slate-950/60 p-2 text-sm">
            <img
                src={piece.icon}
                alt=""
                className={cn('size-11 shrink-0', tooLow && 'opacity-50')}
            />
            <div className="min-w-0 flex-1">
                <p className="font-semibold text-slate-100">{piece.name}</p>
                <p className="text-xs text-slate-300">
                    {SLOT_LABELS[piece.slot] ?? piece.slot} · {bonuses(piece)}
                </p>
                <p
                    className={cn(
                        'text-xs',
                        tooLow ? 'text-red-400' : 'text-slate-400',
                    )}
                >
                    Needs level {piece.level}
                </p>
            </div>
            <button
                type="button"
                disabled={tooLow}
                onClick={() => send(equip(piece.id).url)}
                className="game-button px-3 py-0.5"
            >
                Wear
            </button>
        </li>
    );
}

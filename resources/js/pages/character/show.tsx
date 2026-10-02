import { Head, router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import GameWindow from '@/components/game-window';
import VillageBackdrop from '@/components/village-backdrop';
import { cn } from '@/lib/utils';
import { village } from '@/routes';
import { equip, unequip } from '@/routes/character/gear';
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
};

const SLOT_LABELS: Record<string, string> = {
    weapon: 'Weapon',
    hat: 'Hat',
    armor: 'Armor',
    gloves: 'Gloves',
    belt: 'Belt',
    shoes: 'Shoes',
    amulet: 'Amulet',
    ring: 'Ring',
};

/** "Attack 14-16 · Defense 24" for a piece's non-zero stats. */
function bonuses(piece: Piece): string {
    return [
        piece.max_attack > 0 &&
            `Attack ${piece.min_attack}-${piece.max_attack}`,
        piece.defense > 0 && `Defense ${piece.defense}`,
        piece.max_hp > 0 && `Health +${piece.max_hp}`,
        piece.crit > 0 && `Critical +${piece.crit}%`,
    ]
        .filter(Boolean)
        .join(' · ');
}

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

export default function CharacterPanel({ stats, slots, worn, bag }: Props) {
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
                                    <img
                                        src={
                                            characterAssets(character.avatar)
                                                .portrait
                                        }
                                        alt={character.name}
                                        className="h-[220px] w-auto object-contain drop-shadow-lg"
                                    />
                                    <p className="font-semibold text-amber-200">
                                        {character.name}
                                    </p>
                                    <p className="text-sm text-lime-400">
                                        Level {character.level}
                                    </p>
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

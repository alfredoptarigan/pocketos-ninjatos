import { Head, Link, usePage } from '@inertiajs/react';
import CharacterSprite from '@/components/character-sprite';
import GameWindow from '@/components/game-window';
import InventoryBag, { post } from '@/components/inventory-bag';
import type { BagItem, Piece } from '@/components/inventory-bag';
import VillageBackdrop from '@/components/village-backdrop';
import { bonuses, SLOT_LABELS } from '@/lib/gear';
import { village } from '@/routes';
import { unequip } from '@/routes/character/gear';
import { index as titles } from '@/routes/titles';
import { characterAssets } from '@/types/game';

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
    gear: Piece[];
    items: BagItem[];
    /** False while an outfit is worn: outfits have no create-screen portrait. */
    hasPortrait: boolean;
};

/** The original Inventory: worn gear around the ninja, stats, and the bag below. */
export default function Inventory({
    stats,
    slots,
    worn,
    gear,
    items,
    hasPortrait,
}: Props) {
    const { character } = usePage().props;

    if (!character) {
        return null;
    }

    // The original panel puts four slots on each side of the ninja.
    const half = Math.ceil(slots.length / 2);
    const column = (list: string[]) => (
        <ul className="flex flex-col gap-1.5">
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
        ['Attack', `${stats.minAttack}-${stats.maxAttack}`],
        ['Defense', `${stats.defense}`],
        ['Dodge', `${stats.dodge}%`],
        ['Critical', `${stats.crit}%`],
        ['Block', `${stats.parry}%`],
    ];

    return (
        <>
            <Head title="Inventory" />
            <VillageBackdrop>
                <GameWindow
                    title="Inventory"
                    closeHref={village().url}
                    className="max-w-xl lg:max-w-5xl"
                >
                    {/* Stacked like the original on narrow screens; side by side when there is room. */}
                    <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto]">
                        <div className="flex flex-col gap-3">
                            <div className="flex items-stretch gap-2">
                                {column(slots.slice(0, half))}
                                <div className="flex flex-1 flex-col items-center rounded-md border border-sky-900 bg-gradient-to-b from-sky-950/80 to-slate-950/80 p-2">
                                    <Link
                                        href={titles()}
                                        title="Change title"
                                        className="w-40 rounded border border-slate-500 bg-slate-700/80 text-center text-sm text-slate-300 hover:text-amber-200"
                                    >
                                        {character.title ?? 'No title'}
                                    </Link>
                                    {hasPortrait ? (
                                        <img
                                            src={
                                                characterAssets(
                                                    character.avatar,
                                                ).portrait
                                            }
                                            alt={character.name}
                                            className="h-[150px] w-auto object-contain drop-shadow-lg"
                                        />
                                    ) : (
                                        <CharacterSprite
                                            key={character.avatar}
                                            avatar={character.avatar}
                                            className="h-[150px] w-[130px]"
                                        />
                                    )}
                                    <p className="font-semibold text-amber-200">
                                        {character.name}{' '}
                                        <span className="text-sm text-lime-400">
                                            Lv {character.level}
                                        </span>
                                    </p>
                                </div>
                                {column(slots.slice(half))}
                            </div>

                            <dl
                                aria-label="Battle stats"
                                className="grid grid-cols-4 gap-x-3 rounded border border-sky-900 bg-slate-950/60 px-2 py-1 text-xs"
                            >
                                {rows.map(([label, value]) => (
                                    <div
                                        key={label}
                                        className="flex justify-between gap-1"
                                    >
                                        <dt className="text-slate-400">
                                            {label}
                                        </dt>
                                        <dd>{value}</dd>
                                    </div>
                                ))}
                            </dl>
                        </div>

                        <InventoryBag
                            gear={gear}
                            items={items}
                            level={character.level}
                            weaponClass={character.weapon_class}
                        />
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
                className="grid size-12 place-items-center rounded-md border border-dashed border-sky-800 bg-slate-950/60 text-[10px] text-slate-500"
            >
                {label}
            </div>
        );
    }

    return (
        <button
            type="button"
            onClick={() => post(unequip(piece.id).url)}
            title={`${piece.name} (Lv ${piece.level}): ${bonuses(piece)}. Click to take off.`}
            aria-label={`Take off ${piece.name}`}
            className="size-12 rounded-md border border-emerald-500/80 bg-emerald-950/60 p-1 transition hover:border-amber-300"
        >
            <img src={piece.icon} alt="" className="size-full" />
        </button>
    );
}

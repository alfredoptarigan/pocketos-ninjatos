import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { bonuses, SLOT_LABELS } from '@/lib/gear';
import type { GearStats } from '@/lib/gear';
import { cn } from '@/lib/utils';
import { use } from '@/routes/bag';
import { equip } from '@/routes/character/gear';
import { index as wardrobe } from '@/routes/outfits';

export type Piece = GearStats & {
    id: number;
    code: string;
    name: string;
    icon: string;
};

export type BagItem = {
    id: number;
    name: string;
    icon: string;
    quantity: number;
    max_stack: number;
    restore_hp: number;
    restore_chakra: number;
    restore_energy: number;
};

type Entry =
    | { kind: 'gear'; key: string; piece: Piece }
    | { kind: 'item'; key: string; item: BagItem };

// The original bag: ten pages of 7 x 4 slots.
const PAGES = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];
const SLOTS_PER_PAGE = 28;

export function post(url: string, data: Record<string, number> = {}) {
    router.post(url, data, {
        preserveScroll: true,
        preserveState: true,
        onError: (errors) =>
            toast.error(errors.gear ?? errors.item_id ?? 'That did not work.'),
    });
}

function itemEffect(item: BagItem): string {
    return [
        item.restore_hp > 0 && `Stamina +${item.restore_hp}`,
        item.restore_chakra > 0 && `Chakra +${item.restore_chakra}`,
        item.restore_energy > 0 && `Energy +${item.restore_energy}`,
    ]
        .filter(Boolean)
        .join(' · ');
}

type Props = { gear: Piece[]; items: BagItem[]; level: number };

/** Spare gear first, then item stacks, paged like the original bag. */
export default function InventoryBag({ gear, items, level }: Props) {
    const entries: Entry[] = [
        ...gear.map((piece) => ({
            kind: 'gear' as const,
            key: `g${piece.id}`,
            piece,
        })),
        ...items.map((item) => ({
            kind: 'item' as const,
            key: `i${item.id}`,
            item,
        })),
    ];
    const [page, setPage] = useState(0);
    const [selectedKey, setSelectedKey] = useState<string | null>(null);
    const selected = entries.find((entry) => entry.key === selectedKey);
    const shown = entries.slice(
        page * SLOTS_PER_PAGE,
        (page + 1) * SLOTS_PER_PAGE,
    );

    return (
        <div className="flex flex-col gap-2">
            <div role="tablist" aria-label="Bag pages" className="flex gap-1">
                {PAGES.map((label, index) => (
                    <button
                        key={label}
                        type="button"
                        role="tab"
                        aria-selected={index === page}
                        onClick={() => setPage(index)}
                        className={cn(
                            'flex-1 rounded-t border border-b-0 px-1 text-xs',
                            index === page
                                ? 'border-amber-400 bg-sky-800 text-white'
                                : 'border-amber-900/70 bg-slate-900/80 text-slate-300 hover:text-white',
                        )}
                    >
                        {label}
                    </button>
                ))}
            </div>

            <div className="flex gap-2">
                <div
                    role="listbox"
                    aria-label={`Bag page ${PAGES[page]}`}
                    className="grid grid-cols-[repeat(7,2.75rem)] gap-1"
                >
                    {Array.from({ length: SLOTS_PER_PAGE }, (_, index) => {
                        const entry = shown[index];

                        return entry ? (
                            <BagSlot
                                key={entry.key}
                                entry={entry}
                                selected={entry.key === selectedKey}
                                tooLow={
                                    entry.kind === 'gear' &&
                                    level < entry.piece.level
                                }
                                onSelect={() => setSelectedKey(entry.key)}
                            />
                        ) : (
                            <div
                                key={`empty-${index}`}
                                aria-hidden
                                className="size-11 rounded border border-sky-950 bg-slate-950/50"
                            />
                        );
                    })}
                </div>

                <div className="flex w-24 flex-col gap-1">
                    <SideButton label="Compose" />
                    <SideButton label="Multi-Sell" />
                    <SideButton label="Collection" />
                    <Link
                        href={wardrobe()}
                        className="game-button py-0.5 text-center text-sm"
                    >
                        Wardrobe
                    </Link>
                    <SideButton label="Reroll" />
                    <button
                        type="button"
                        onClick={() => {
                            setPage(0);
                            toast.info('Your bag is already in order.');
                        }}
                        className="game-button py-0.5 text-sm"
                    >
                        Sort
                    </button>
                </div>
            </div>

            <Detail entry={selected} level={level} />
        </div>
    );
}

function SideButton({ label }: { label: string }) {
    return (
        <button
            type="button"
            onClick={() => toast.info(`${label} is coming soon.`)}
            className="game-button py-0.5 text-sm opacity-70"
        >
            {label}
        </button>
    );
}

type SlotProps = {
    entry: Entry;
    selected: boolean;
    tooLow: boolean;
    onSelect: () => void;
};

function BagSlot({ entry, selected, tooLow, onSelect }: SlotProps) {
    const { name, icon } = entry.kind === 'gear' ? entry.piece : entry.item;

    return (
        <button
            type="button"
            role="option"
            aria-selected={selected}
            title={name}
            onClick={onSelect}
            onDoubleClick={() =>
                entry.kind === 'gear'
                    ? post(equip(entry.piece.id).url)
                    : post(use().url, { item_id: entry.item.id })
            }
            className={cn(
                'relative size-11 rounded border p-0.5',
                entry.kind === 'gear' ? 'bg-emerald-950/70' : 'bg-slate-950/70',
                selected
                    ? 'border-amber-300 shadow-[0_0_8px_rgba(252,211,77,0.7)]'
                    : 'border-sky-900 hover:border-amber-500',
            )}
        >
            <img
                src={icon}
                alt={name}
                className={cn(
                    'size-full object-contain',
                    tooLow && 'opacity-50',
                )}
            />
            {entry.kind === 'item' && entry.item.quantity > 1 && (
                <span className="absolute right-0.5 bottom-0 text-[10px] font-bold text-white [text-shadow:0_0_2px_#000]">
                    x{entry.item.quantity}
                </span>
            )}
        </button>
    );
}

function Detail({ entry, level }: { entry?: Entry; level: number }) {
    if (!entry) {
        return (
            <p className="min-h-14 rounded border border-sky-900 bg-slate-950/60 p-2 text-xs text-slate-400">
                Select an item. Double-click gear to wear it or a potion to use
                it.
            </p>
        );
    }

    if (entry.kind === 'gear') {
        const { piece } = entry;
        const tooLow = level < piece.level;

        return (
            <div className="flex min-h-14 items-center gap-3 rounded border border-sky-900 bg-slate-950/60 p-2 text-sm">
                <img src={piece.icon} alt="" className="size-10" />
                <div className="min-w-0 flex-1">
                    <p className="font-semibold text-slate-100">{piece.name}</p>
                    <p className="text-xs text-slate-300">
                        {SLOT_LABELS[piece.slot] ?? piece.slot} ·{' '}
                        {bonuses(piece)}
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
                    onClick={() => post(equip(piece.id).url)}
                    className="game-button px-3 py-0.5"
                >
                    Wear
                </button>
            </div>
        );
    }

    const { item } = entry;
    const usable = item.restore_hp > 0 || item.restore_chakra > 0;

    return (
        <div className="flex min-h-14 items-center gap-3 rounded border border-sky-900 bg-slate-950/60 p-2 text-sm">
            <img src={item.icon} alt="" className="size-10 object-contain" />
            <div className="min-w-0 flex-1">
                <p className="font-semibold text-amber-200">{item.name}</p>
                <p className="text-xs text-slate-300">{itemEffect(item)}</p>
                <p className="text-xs text-slate-400">
                    {item.quantity}/{item.max_stack} carried
                </p>
            </div>
            {usable ? (
                <button
                    type="button"
                    onClick={() => post(use().url, { item_id: item.id })}
                    className="game-button px-4 py-0.5"
                >
                    Use
                </button>
            ) : (
                <p className="text-xs text-slate-400">Not needed yet.</p>
            )}
        </div>
    );
}

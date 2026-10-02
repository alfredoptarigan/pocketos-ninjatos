import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import GameWindow from '@/components/game-window';
import VillageBackdrop from '@/components/village-backdrop';
import { cn } from '@/lib/utils';
import { village } from '@/routes';
import { use } from '@/routes/bag';

type BagItem = {
    id: number;
    name: string;
    icon: string;
    quantity: number;
    max_stack: number;
    restore_hp: number;
    restore_chakra: number;
    restore_energy: number;
};

// Matches the original bag grid; empty slots show how much room is left.
const BAG_SLOTS = 40;

function isUsable(item: BagItem): boolean {
    return item.restore_hp > 0 || item.restore_chakra > 0;
}

function effectText(item: BagItem): string {
    return [
        item.restore_hp > 0 && `Stamina +${item.restore_hp}`,
        item.restore_chakra > 0 && `Chakra +${item.restore_chakra}`,
        item.restore_energy > 0 && `Energy +${item.restore_energy}`,
    ]
        .filter(Boolean)
        .join(' · ');
}

export default function Bag({ items }: { items: BagItem[] }) {
    const [selectedId, setSelectedId] = useState(items[0]?.id ?? null);
    const selected = items.find((item) => item.id === selectedId);
    const emptySlots = Math.max(0, BAG_SLOTS - items.length);

    return (
        <>
            <Head title="Bag" />
            <VillageBackdrop>
                <GameWindow title="Bag" closeHref={village().url}>
                    <div
                        role="listbox"
                        aria-label="Bag"
                        className="grid grid-cols-8 gap-2 sm:grid-cols-10"
                    >
                        {items.map((item) => (
                            <button
                                key={item.id}
                                type="button"
                                role="option"
                                aria-selected={item.id === selectedId}
                                title={item.name}
                                onClick={() => setSelectedId(item.id)}
                                className={cn(
                                    'relative aspect-square rounded-md border-2 bg-slate-950/70 p-1',
                                    item.id === selectedId
                                        ? 'border-amber-300 shadow-[0_0_10px_rgba(252,211,77,0.7)]'
                                        : 'border-amber-900/80 hover:border-amber-500',
                                )}
                            >
                                <img
                                    src={item.icon}
                                    alt={item.name}
                                    className="size-full object-contain"
                                />
                                <span className="absolute right-0.5 bottom-0 text-xs font-bold text-white [text-shadow:0_0_2px_#000]">
                                    {item.quantity}
                                </span>
                            </button>
                        ))}
                        {Array.from({ length: emptySlots }, (_, index) => (
                            <div
                                key={`empty-${index}`}
                                aria-hidden
                                className="aspect-square rounded-md border-2 border-amber-950/60 bg-slate-950/40"
                            />
                        ))}
                    </div>

                    <div className="mt-4 min-h-16 rounded-md border border-amber-900/80 bg-slate-950/60 p-3">
                        {selected ? (
                            <div className="flex items-center gap-4">
                                <img
                                    src={selected.icon}
                                    alt=""
                                    className="size-12 object-contain"
                                />
                                <div className="flex-1">
                                    <p className="font-semibold text-amber-200">
                                        {selected.name}
                                    </p>
                                    <p className="text-sm text-slate-300">
                                        {effectText(selected)}
                                    </p>
                                    <p className="text-xs text-slate-400">
                                        {selected.quantity}/{selected.max_stack}{' '}
                                        carried
                                    </p>
                                </div>
                                {isUsable(selected) ? (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            router.post(
                                                use().url,
                                                { item_id: selected.id },
                                                {
                                                    preserveScroll: true,
                                                    preserveState: true,
                                                },
                                            )
                                        }
                                        className="game-button px-5 py-1"
                                    >
                                        Use
                                    </button>
                                ) : (
                                    <p className="text-xs text-slate-400">
                                        Energy items are not needed yet.
                                    </p>
                                )}
                            </div>
                        ) : (
                            <p className="text-sm text-slate-300">
                                Your bag is empty. Visit the Pharmacy to stock
                                up.
                            </p>
                        )}
                    </div>
                </GameWindow>
            </VillageBackdrop>
        </>
    );
}

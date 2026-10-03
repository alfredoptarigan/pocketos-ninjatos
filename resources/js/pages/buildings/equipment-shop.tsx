import { Head, router, usePage } from '@inertiajs/react';
import { Coins } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import GameWindow from '@/components/game-window';
import ShopKeeper from '@/components/shop-keeper';
import VillageBackdrop from '@/components/village-backdrop';
import {
    bonuses,
    canWield,
    SLOT_LABELS,
    slotLabel,
    WEAPON_CLASS_LABELS,
} from '@/lib/gear';
import type { GearStats } from '@/lib/gear';
import { cn } from '@/lib/utils';
import { village as villageRoute } from '@/routes';
import { buy, sell } from '@/routes/equipment-shop';

type Ware = GearStats & {
    id: number;
    code: string;
    name: string;
    icon: string;
    buy_price: number;
    sell_price: number;
};

type Props = {
    stock: Ware[];
    bag: Ware[];
};

// The original keeper's greeting (buildnpc n11005), translated.
const KEEPER_LINE =
    'Step right up! Nobody should travel without weapons and armor. Every kind of gear, genuine goods at honest prices.';

const SLOTS = ['all', ...Object.keys(SLOT_LABELS)];

function post(url: string) {
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

export default function EquipmentShop({ stock, bag }: Props) {
    const { character } = usePage().props;
    const [mode, setMode] = useState<'buy' | 'sell'>('buy');
    const [slot, setSlot] = useState('all');
    const wares = (mode === 'buy' ? stock : bag).filter(
        (ware) => slot === 'all' || ware.slot === slot,
    );

    return (
        <>
            <Head title="Equipment Shop" />
            <VillageBackdrop>
                <GameWindow
                    title="Equipment Shop"
                    closeHref={villageRoute().url}
                >
                    <div className="grid gap-6 md:grid-cols-[200px_1fr]">
                        <ShopKeeper
                            line={KEEPER_LINE}
                            portrait="/game-assets/npcs/equipment.png"
                        />

                        <div className="flex min-w-0 flex-col gap-3">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div role="tablist" className="flex gap-2">
                                    {(['buy', 'sell'] as const).map((m) => (
                                        <button
                                            key={m}
                                            type="button"
                                            role="tab"
                                            aria-selected={mode === m}
                                            onClick={() => setMode(m)}
                                            className={cn(
                                                'game-button px-4 py-1',
                                                mode === m && 'brightness-125',
                                            )}
                                        >
                                            {m === 'buy' ? 'Buy' : 'Sell'}
                                        </button>
                                    ))}
                                </div>
                                <span className="flex items-center gap-1 font-semibold text-amber-300">
                                    <Coins className="size-4" />
                                    {character?.gold.toLocaleString(
                                        'en-US',
                                    )}{' '}
                                    gold
                                </span>
                            </div>

                            <div
                                role="tablist"
                                aria-label="Slots"
                                className="flex flex-wrap gap-1 text-sm"
                            >
                                {SLOTS.map((s) => (
                                    <button
                                        key={s}
                                        type="button"
                                        role="tab"
                                        aria-selected={slot === s}
                                        onClick={() => setSlot(s)}
                                        className={cn(
                                            'rounded border px-2 py-0.5',
                                            slot === s
                                                ? 'border-amber-400 bg-amber-900/60 text-amber-100'
                                                : 'border-slate-600 text-slate-300 hover:border-amber-600',
                                        )}
                                    >
                                        {s === 'all' ? 'All' : SLOT_LABELS[s]}
                                    </button>
                                ))}
                            </div>

                            {wares.length === 0 ? (
                                <p className="text-sm text-slate-400">
                                    {mode === 'buy'
                                        ? 'Nothing for sale here.'
                                        : 'No spare gear to sell. Worn gear must be taken off first.'}
                                </p>
                            ) : (
                                <ul className="grid max-h-[55vh] gap-2 overflow-y-auto pr-1">
                                    {wares.map((ware) => (
                                        <WareRow
                                            key={`${mode}-${ware.id}`}
                                            ware={ware}
                                            mode={mode}
                                            level={character?.level ?? 0}
                                            weaponClass={
                                                character?.weapon_class ?? null
                                            }
                                            gold={character?.gold ?? 0}
                                        />
                                    ))}
                                </ul>
                            )}
                        </div>
                    </div>
                </GameWindow>
            </VillageBackdrop>
        </>
    );
}

type RowProps = {
    ware: Ware;
    mode: 'buy' | 'sell';
    level: number;
    weaponClass: string | null;
    gold: number;
};

function WareRow({ ware, mode, level, weaponClass, gold }: RowProps) {
    const price = mode === 'buy' ? ware.buy_price : ware.sell_price;
    const tooPoor = mode === 'buy' && gold < price;

    return (
        <li className="flex items-center gap-3 rounded-md border border-amber-900/70 bg-slate-950/60 p-2 text-sm">
            <img src={ware.icon} alt="" className="size-11 shrink-0" />
            <div className="min-w-0 flex-1">
                <p className="font-semibold text-slate-100">{ware.name}</p>
                <p className="text-xs text-slate-300">
                    {slotLabel(ware)} · {bonuses(ware)}
                </p>
                <p
                    className={cn(
                        'text-xs',
                        level < ware.level ? 'text-red-400' : 'text-slate-400',
                    )}
                >
                    Needs level {ware.level}
                </p>
                {!canWield(ware, weaponClass) && weaponClass && (
                    <p className="text-xs text-red-400">
                        Your outfit fights with{' '}
                        {WEAPON_CLASS_LABELS[weaponClass]} weapons
                    </p>
                )}
            </div>
            <span
                className={cn(
                    'flex items-center gap-1 font-semibold',
                    tooPoor ? 'text-red-400' : 'text-amber-300',
                )}
            >
                <Coins className="size-4" />
                {price.toLocaleString('en-US')}
            </span>
            <button
                type="button"
                disabled={tooPoor}
                onClick={() =>
                    post(mode === 'buy' ? buy(ware.id).url : sell(ware.id).url)
                }
                className="game-button px-3 py-0.5"
            >
                {mode === 'buy' ? 'Buy' : 'Sell'}
            </button>
        </li>
    );
}

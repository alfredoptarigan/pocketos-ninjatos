import { Form, Head, usePage } from '@inertiajs/react';
import { Coins } from 'lucide-react';
import { useState } from 'react';
import GameWindow from '@/components/game-window';
import InputError from '@/components/input-error';
import { cn } from '@/lib/utils';
import { village as villageRoute } from '@/routes';
import { buy } from '@/routes/pharmacy';
import type { ShopItem } from '@/types/game';

type Props = {
    items: ShopItem[];
    owned: Record<number, number>;
    village: string;
};

const TABS: { title: string; matches: (item: ShopItem) => boolean }[] = [
    {
        title: 'Stamina',
        matches: (i) => i.restore_hp > 0 && i.restore_chakra === 0,
    },
    {
        title: 'Chakra',
        matches: (i) => i.restore_chakra > 0 && i.restore_hp === 0,
    },
    {
        title: 'Restoration',
        matches: (i) => i.restore_hp > 0 && i.restore_chakra > 0,
    },
    { title: 'Energy', matches: (i) => i.restore_energy > 0 },
];

// The original keeper's greeting (buildnpc n11004), translated.
const KEEPER_LINE =
    'Need gems to make your gear shine, or hidden weapons for battle? Or just food and potions? My shop has it all.';

function effectText(item: ShopItem): string {
    return [
        item.restore_hp > 0 && `Stamina +${item.restore_hp}`,
        item.restore_chakra > 0 && `Chakra +${item.restore_chakra}`,
        item.restore_energy > 0 && `Energy +${item.restore_energy}`,
    ]
        .filter(Boolean)
        .join(' · ');
}

export default function Pharmacy({ items, owned, village }: Props) {
    const { character } = usePage().props;
    const [tab, setTab] = useState(0);
    const stock = items.filter(TABS[tab].matches);
    const [selectedId, setSelectedId] = useState<number | null>(
        stock[0]?.id ?? null,
    );
    const selected = stock.find((item) => item.id === selectedId) ?? stock[0];

    const chooseTab = (index: number) => {
        setTab(index);
        setSelectedId(items.find(TABS[index].matches)?.id ?? null);
    };

    return (
        <>
            <Head title="Pharmacy" />
            <div
                className="relative flex flex-1 items-center justify-center overflow-hidden rounded-xl bg-cover bg-center px-4 py-14"
                style={{
                    backgroundImage: `url(/game-assets/villages/${village}.jpg)`,
                }}
            >
                <div className="absolute inset-0 bg-black/55" aria-hidden />
                <GameWindow title="Pharmacy" closeHref={villageRoute().url}>
                    <div className="grid gap-6 md:grid-cols-[200px_1fr]">
                        <Keeper />

                        <div className="flex flex-col gap-4">
                            <div
                                role="tablist"
                                aria-label="Potion types"
                                className="flex flex-wrap gap-2"
                            >
                                {TABS.map((t, index) => (
                                    <button
                                        key={t.title}
                                        type="button"
                                        role="tab"
                                        aria-selected={index === tab}
                                        onClick={() => chooseTab(index)}
                                        className="game-button px-3 py-0.5 text-sm"
                                    >
                                        {t.title}
                                    </button>
                                ))}
                            </div>

                            <div
                                role="listbox"
                                aria-label="Items for sale"
                                className="grid grid-cols-5 gap-2 sm:grid-cols-6 lg:grid-cols-8"
                            >
                                {stock.map((item) => (
                                    <button
                                        key={item.id}
                                        type="button"
                                        role="option"
                                        aria-selected={item.id === selected?.id}
                                        title={`${item.name} — ${item.price} gold`}
                                        onClick={() => setSelectedId(item.id)}
                                        className={cn(
                                            'relative aspect-square rounded-md border-2 bg-slate-950/70 p-1 transition',
                                            item.id === selected?.id
                                                ? 'border-amber-300 shadow-[0_0_10px_rgba(252,211,77,0.7)]'
                                                : 'border-amber-900/80 hover:border-amber-500',
                                        )}
                                    >
                                        <img
                                            src={item.icon}
                                            alt={item.name}
                                            className="size-full object-contain"
                                        />
                                        {(owned[item.id] ?? 0) > 0 && (
                                            <span className="absolute right-0.5 bottom-0 text-xs font-bold text-white [text-shadow:0_0_2px_#000]">
                                                {owned[item.id]}
                                            </span>
                                        )}
                                    </button>
                                ))}
                            </div>

                            {selected && (
                                <ItemDetail
                                    key={selected.id}
                                    item={selected}
                                    owned={owned[selected.id] ?? 0}
                                />
                            )}

                            <p className="flex items-center justify-end gap-2 font-semibold text-amber-300">
                                <Coins className="size-4" />
                                {character?.gold.toLocaleString('en-US')} gold
                            </p>
                        </div>
                    </div>
                </GameWindow>
            </div>
        </>
    );
}

function Keeper() {
    return (
        <div className="flex flex-col items-center gap-3">
            <div className="relative rounded-lg border-2 border-amber-200/80 bg-amber-50 p-3 text-sm text-amber-950 shadow">
                {KEEPER_LINE}
                <span
                    aria-hidden
                    className="absolute -bottom-2 left-1/2 size-4 -translate-x-1/2 rotate-45 border-r-2 border-b-2 border-amber-200/80 bg-amber-50"
                />
            </div>
            <img
                src="/game-assets/npcs/pharmacy.png"
                alt="Shopkeeper"
                className="w-40 drop-shadow-lg"
            />
            <p className="text-sm font-semibold text-amber-200">Shopkeeper</p>
        </div>
    );
}

function ItemDetail({ item, owned }: { item: ShopItem; owned: number }) {
    const room = item.max_stack - owned;

    return (
        <Form
            {...buy.form()}
            options={{ preserveScroll: true, preserveState: true }}
            className="flex flex-wrap items-center gap-4 rounded-md border border-amber-900/80 bg-slate-950/60 p-3"
        >
            {({ processing, errors }) => (
                <>
                    <img
                        src={item.icon}
                        alt=""
                        className="size-14 object-contain"
                    />
                    <div className="min-w-40 flex-1">
                        <p className="font-semibold text-amber-200">
                            {item.name}
                        </p>
                        <p className="text-sm text-slate-300">
                            {effectText(item)}
                        </p>
                        <p className="text-xs text-slate-400">
                            {item.price} gold each · Owned {owned}/
                            {item.max_stack}
                        </p>
                        <InputError
                            message={errors.quantity ?? errors.item_id}
                        />
                    </div>
                    <input type="hidden" name="item_id" value={item.id} />
                    <input
                        type="number"
                        name="quantity"
                        defaultValue={1}
                        min={1}
                        max={Math.max(1, room)}
                        aria-label={`Quantity of ${item.name}`}
                        disabled={room <= 0}
                        className="w-16 rounded border border-amber-900 bg-slate-900 px-2 py-1 text-slate-100"
                    />
                    <button
                        type="submit"
                        disabled={processing || room <= 0}
                        className="game-button min-w-20 px-4 py-1"
                    >
                        {room <= 0 ? 'Bag full' : 'Buy'}
                    </button>
                </>
            )}
        </Form>
    );
}

import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Coins } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { desa } from '@/routes';
import { buy } from '@/routes/apotek';
import type { ShopItem } from '@/types/game';

type Props = {
    items: ShopItem[];
    owned: Record<number, number>;
};

const SECTIONS: { title: string; matches: (item: ShopItem) => boolean }[] = [
    {
        title: 'Pemulih Stamina',
        matches: (i) => i.restore_hp > 0 && i.restore_chakra === 0,
    },
    {
        title: 'Pemulih Cakra',
        matches: (i) => i.restore_chakra > 0 && i.restore_hp === 0,
    },
    {
        title: 'Pemulih Stamina & Cakra',
        matches: (i) => i.restore_hp > 0 && i.restore_chakra > 0,
    },
    { title: 'Obat Energi', matches: (i) => i.restore_energy > 0 },
];

function effectText(item: ShopItem): string {
    return [
        item.restore_hp > 0 && `Stamina +${item.restore_hp}`,
        item.restore_chakra > 0 && `Cakra +${item.restore_chakra}`,
        item.restore_energy > 0 && `Energi +${item.restore_energy}`,
    ]
        .filter(Boolean)
        .join(' · ');
}

export default function Apotek({ items, owned }: Props) {
    const { character } = usePage().props;

    return (
        <>
            <Head title="Apotek" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <header className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Button
                            asChild
                            variant="outline"
                            size="icon"
                            aria-label="Kembali ke desa"
                        >
                            <Link href={desa()}>
                                <ArrowLeft />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-semibold">Apotek</h1>
                            <p className="text-muted-foreground">
                                Ramuan untuk memulihkan stamina, cakra, dan
                                energi.
                            </p>
                        </div>
                    </div>
                    <p className="flex items-center gap-2 rounded-full bg-amber-100 px-4 py-2 font-semibold text-amber-900 dark:bg-amber-950 dark:text-amber-200">
                        <Coins className="size-4" />
                        {character?.gold.toLocaleString('id-ID')} koin
                    </p>
                </header>

                {SECTIONS.map((section) => {
                    const stock = items.filter(section.matches);

                    return (
                        stock.length > 0 && (
                            <section
                                key={section.title}
                                className="flex flex-col gap-3"
                            >
                                <h2 className="text-lg font-semibold">
                                    {section.title}
                                </h2>
                                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                    {stock.map((item) => (
                                        <ItemCard
                                            key={item.id}
                                            item={item}
                                            owned={owned[item.id] ?? 0}
                                        />
                                    ))}
                                </div>
                            </section>
                        )
                    );
                })}
            </div>
        </>
    );
}

function ItemCard({ item, owned }: { item: ShopItem; owned: number }) {
    const full = owned >= item.max_stack;

    return (
        <Form
            {...buy.form()}
            options={{ preserveScroll: true }}
            className="flex flex-col gap-2 rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border"
        >
            {({ processing, errors }) => (
                <>
                    <div className="flex items-center gap-3">
                        <img
                            src={item.icon}
                            alt=""
                            className="size-12 rounded-md bg-muted object-contain p-1"
                        />
                        <div className="min-w-0 flex-1">
                            <p className="truncate font-medium">{item.name}</p>
                            <p className="text-sm text-muted-foreground">
                                {effectText(item)}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Dimiliki {owned}/{item.max_stack}
                            </p>
                        </div>
                        <p className="font-semibold whitespace-nowrap text-amber-700 dark:text-amber-300">
                            {item.price} koin
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <input type="hidden" name="item_id" value={item.id} />
                        <Input
                            type="number"
                            name="quantity"
                            defaultValue={1}
                            min={1}
                            max={Math.max(1, item.max_stack - owned)}
                            aria-label={`Jumlah ${item.name}`}
                            className="w-20"
                            disabled={full}
                        />
                        <Button
                            type="submit"
                            className="flex-1"
                            disabled={processing || full}
                        >
                            {processing && <Spinner />}
                            {full ? 'Tas penuh' : 'Beli'}
                        </Button>
                    </div>
                    <InputError message={errors.quantity ?? errors.item_id} />
                </>
            )}
        </Form>
    );
}

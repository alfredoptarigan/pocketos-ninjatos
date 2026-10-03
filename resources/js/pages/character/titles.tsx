import { Head, router } from '@inertiajs/react';
import GameWindow from '@/components/game-window';
import VillageBackdrop from '@/components/village-backdrop';
import { cn } from '@/lib/utils';
import { bag as inventory } from '@/routes';
import { takeOff, wear } from '@/routes/titles';
import { describeBonus } from '@/types/game';
import type { Title } from '@/types/game';

type Props = {
    owned: Title[];
    /** Titles not earned yet that this server can award. */
    locked: (Title & { how: string })[];
    worn: number | null;
};

const send = (url: string) => router.post(url, {}, { preserveScroll: true });

export default function Titles({ owned, locked, worn }: Props) {
    return (
        <>
            <Head title="Titles" />
            <VillageBackdrop>
                <GameWindow title="Titles" closeHref={inventory().url}>
                    <div className="flex flex-col gap-4 text-sm">
                        <section aria-label="Earned titles">
                            <h2 className="mb-1 font-semibold text-amber-200">
                                Earned
                            </h2>
                            <ul className="grid gap-1 sm:grid-cols-2">
                                <li>
                                    <TitleButton
                                        name="No title"
                                        detail="No bonus"
                                        selected={worn === null}
                                        onSelect={() => send(takeOff().url)}
                                    />
                                </li>
                                {owned.map((title) => (
                                    <li key={title.id}>
                                        <TitleButton
                                            name={title.name}
                                            detail={describeBonus(title.bonus)}
                                            selected={title.id === worn}
                                            onSelect={() =>
                                                send(wear(title.id).url)
                                            }
                                        />
                                    </li>
                                ))}
                            </ul>
                        </section>

                        {locked.length > 0 && (
                            <section aria-label="Titles to earn">
                                <h2 className="mb-1 font-semibold text-slate-300">
                                    To earn
                                </h2>
                                <ul className="grid gap-1 sm:grid-cols-2">
                                    {locked.map((title) => (
                                        <li
                                            key={title.id}
                                            className="rounded border border-slate-700 bg-slate-950/60 px-2 py-1 text-slate-400"
                                        >
                                            <p className="font-semibold text-slate-300">
                                                {title.name}
                                            </p>
                                            <p className="text-xs">
                                                {describeBonus(title.bonus)}
                                            </p>
                                            <p className="text-xs">
                                                {title.how}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        )}
                    </div>
                </GameWindow>
            </VillageBackdrop>
        </>
    );
}

function TitleButton({
    name,
    detail,
    selected,
    onSelect,
}: {
    name: string;
    detail: string;
    selected: boolean;
    onSelect: () => void;
}) {
    return (
        <button
            type="button"
            aria-pressed={selected}
            onClick={selected ? undefined : onSelect}
            className={cn(
                'w-full rounded border bg-slate-950/70 px-2 py-1 text-left transition',
                selected
                    ? 'border-amber-300 ring-1 ring-amber-300'
                    : 'border-sky-800 hover:border-amber-500',
            )}
        >
            <p className="font-semibold text-amber-200">
                {name}
                {selected && ' (worn)'}
            </p>
            <p className="text-xs text-slate-400">{detail}</p>
        </button>
    );
}

import { Head, router, usePage } from '@inertiajs/react';
import GameWindow from '@/components/game-window';
import StatBar from '@/components/stat-bar';
import VillageBackdrop from '@/components/village-backdrop';
import { cn } from '@/lib/utils';
import { bag as inventory } from '@/routes';
import { exchange } from '@/routes/honor';

type Rank = { medals: number; exp: number };

type Props = {
    honor: number;
    medals: number;
    rank: number;
    /** Honor needed per rank. */
    perRank: number;
    exchangesLeft: number;
    /** Exchange rate per rank, rank 1 first. */
    ranks: Rank[];
    sources: { tower: number; dungeon: number };
};

export default function Honor({
    honor,
    medals,
    rank,
    perRank,
    exchangesLeft,
    ranks,
    sources,
}: Props) {
    const { errors } = usePage().props;
    const error = Object.values(errors ?? {})[0];
    const current = ranks[rank - 1];
    const maxRank = rank === ranks.length;

    return (
        <>
            <Head title="My Honor" />
            <VillageBackdrop>
                <GameWindow title="My Honor" closeHref={inventory().url}>
                    <div className="grid gap-4 text-sm md:grid-cols-2">
                        <section className="flex flex-col gap-2">
                            <p className="text-lg font-semibold text-amber-200">
                                Rank {rank}
                            </p>
                            <p className="text-slate-300">
                                Total honor: {honor.toLocaleString('en-US')} ·
                                Medals: {medals.toLocaleString('en-US')}
                            </p>
                            {!maxRank && (
                                <StatBar
                                    label="Next rank"
                                    value={honor - (rank - 1) * perRank}
                                    max={perRank}
                                    color="bg-violet-500"
                                />
                            )}
                            <p className="text-xs text-slate-400">
                                Earn {sources.tower} honor for every new
                                Training Tower floor and {sources.dungeon} for
                                every dungeon cleared, plus as many medals.
                            </p>

                            <div className="mt-2 rounded border border-violet-700 bg-slate-950/70 p-2">
                                <p className="font-semibold text-violet-200">
                                    Honor Exchange
                                </p>
                                <p className="text-slate-300">
                                    At your rank, {current.medals} medals buy{' '}
                                    {current.exp.toLocaleString('en-US')} EXP.
                                </p>
                                <p className="text-xs text-slate-400">
                                    {exchangesLeft} exchanges left today
                                </p>
                                <button
                                    type="button"
                                    className="game-button mt-1 px-3 py-0.5"
                                    disabled={
                                        exchangesLeft < 1 ||
                                        medals < current.medals
                                    }
                                    onClick={() =>
                                        router.post(
                                            exchange().url,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Exchange EXP
                                </button>
                                {error && (
                                    <p
                                        role="alert"
                                        className="mt-1 text-xs text-red-300"
                                    >
                                        {error}
                                    </p>
                                )}
                            </div>
                        </section>

                        <table className="text-xs">
                            <thead className="text-slate-400">
                                <tr>
                                    <th className="text-left">Rank</th>
                                    <th className="text-right">Honor</th>
                                    <th className="text-right">Medals</th>
                                    <th className="text-right">EXP</th>
                                </tr>
                            </thead>
                            <tbody>
                                {ranks.map((row, index) => (
                                    <tr
                                        key={index}
                                        className={cn(
                                            index + 1 === rank
                                                ? 'text-amber-200'
                                                : 'text-slate-300',
                                        )}
                                    >
                                        <td>{index + 1}</td>
                                        <td className="text-right">
                                            {(index * perRank).toLocaleString(
                                                'en-US',
                                            )}
                                        </td>
                                        <td className="text-right">
                                            {row.medals}
                                        </td>
                                        <td className="text-right">
                                            {row.exp.toLocaleString('en-US')}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </GameWindow>
            </VillageBackdrop>
        </>
    );
}

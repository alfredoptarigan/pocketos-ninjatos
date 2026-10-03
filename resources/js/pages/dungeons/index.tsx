import { Head, Link, usePage } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import GameWindow from '@/components/game-window';
import VillageBackdrop from '@/components/village-backdrop';
import { cn } from '@/lib/utils';
import { village } from '@/routes';
import { show } from '@/routes/dungeons';
import { DIFFICULTY_STYLE } from '@/types/game';
import type { Difficulty } from '@/types/game';

type DungeonCard = {
    id: number;
    name: string;
    difficulty: Difficulty;
    min_level: number;
    max_level: number;
    picture: string;
    daily_runs: number;
    runs_left: number;
    reward_exp: number;
    reward_gold: number;
    stages: number;
};

type Props = { dungeons: DungeonCard[]; active: number | null };

export default function Dungeons({ dungeons, active }: Props) {
    const { character } = usePage().props;
    const level = character?.level ?? 1;

    return (
        <>
            <Head title="Dungeons" />
            <VillageBackdrop>
                <GameWindow title="Dungeons" closeHref={village().url}>
                    <p className="mb-3 text-center text-sm text-slate-300">
                        Fight through each stage&apos;s waves. Wounds carry over
                        between waves, and a defeat ends the run.
                    </p>
                    <ul className="grid max-h-[65vh] grid-cols-1 gap-3 overflow-y-auto pr-1 sm:grid-cols-2 lg:grid-cols-3">
                        {dungeons.map((dungeon) => {
                            const locked = level < dungeon.min_level;

                            return (
                                <li key={dungeon.id}>
                                    <Link
                                        href={show(dungeon.id)}
                                        className={cn(
                                            'group block overflow-hidden rounded-md border-2 bg-slate-950/70 transition',
                                            dungeon.id === active
                                                ? 'border-amber-300 shadow-[0_0_10px_rgba(252,211,77,0.6)]'
                                                : 'border-sky-900 hover:border-amber-500',
                                        )}
                                    >
                                        <div className="relative">
                                            <img
                                                src={dungeon.picture}
                                                alt=""
                                                className={cn(
                                                    'aspect-[5/3] w-full object-cover',
                                                    locked && 'grayscale',
                                                )}
                                            />
                                            <span
                                                className={cn(
                                                    'absolute top-1 left-1 rounded px-1.5 text-xs font-semibold capitalize',
                                                    DIFFICULTY_STYLE[
                                                        dungeon.difficulty
                                                    ],
                                                )}
                                            >
                                                {dungeon.difficulty}
                                            </span>
                                            {dungeon.id === active && (
                                                <span className="absolute top-1 right-1 rounded bg-amber-400 px-1.5 text-xs font-semibold text-black">
                                                    In progress
                                                </span>
                                            )}
                                            {locked && (
                                                <span className="absolute inset-0 grid place-items-center bg-black/50 text-sm text-slate-200">
                                                    <span className="flex items-center gap-1">
                                                        <Lock className="size-4" />{' '}
                                                        Level{' '}
                                                        {dungeon.min_level}
                                                    </span>
                                                </span>
                                            )}
                                        </div>
                                        <div className="p-2 text-sm">
                                            <p className="font-semibold text-amber-200">
                                                {dungeon.name}
                                            </p>
                                            <p className="text-xs text-slate-400">
                                                Lv {dungeon.min_level}-
                                                {dungeon.max_level} ·{' '}
                                                {dungeon.stages}{' '}
                                                {dungeon.stages === 1
                                                    ? 'stage'
                                                    : 'stages'}{' '}
                                                · Runs today {dungeon.runs_left}
                                                /{dungeon.daily_runs}
                                            </p>
                                        </div>
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                </GameWindow>
            </VillageBackdrop>
        </>
    );
}

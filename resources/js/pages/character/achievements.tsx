import { Head, Link } from '@inertiajs/react';
import GameWindow from '@/components/game-window';
import StatBar from '@/components/stat-bar';
import VillageBackdrop from '@/components/village-backdrop';
import { cn } from '@/lib/utils';
import { bag as inventory } from '@/routes';
import { index as titles } from '@/routes/titles';

type Achievement = {
    id: number;
    name: string;
    goal: string;
    target: number;
    progress: number;
    points: number;
    /** Name of the title it grants. */
    title: string | null;
    completed_at: string | null;
};

type Props = {
    achievements: Achievement[];
    points: number;
};

export default function Achievements({ achievements, points }: Props) {
    const done = achievements.filter((a) => a.completed_at).length;

    return (
        <>
            <Head title="Achievements" />
            <VillageBackdrop>
                <GameWindow title="Achievements" closeHref={inventory().url}>
                    <div className="flex flex-col gap-3 text-sm">
                        <p className="flex justify-between text-amber-200">
                            <span>
                                {done} / {achievements.length} completed
                            </span>
                            <span>{points} achievement points</span>
                        </p>
                        <ul className="grid gap-2 sm:grid-cols-2">
                            {achievements.map((achievement) => (
                                <AchievementRow
                                    key={achievement.id}
                                    achievement={achievement}
                                />
                            ))}
                        </ul>
                        <p className="text-xs text-slate-400">
                            More achievements arrive with the arena, missions
                            and pets.{' '}
                            <Link
                                href={titles()}
                                className="text-amber-300 underline"
                            >
                                Titles
                            </Link>
                        </p>
                    </div>
                </GameWindow>
            </VillageBackdrop>
        </>
    );
}

function AchievementRow({ achievement }: { achievement: Achievement }) {
    const completed = achievement.completed_at !== null;

    return (
        <li
            className={cn(
                'flex flex-col gap-1 rounded border bg-slate-950/70 px-2 py-1',
                completed ? 'border-amber-500' : 'border-slate-700',
            )}
        >
            <p className="flex justify-between font-semibold">
                <span
                    className={completed ? 'text-amber-200' : 'text-slate-300'}
                >
                    {achievement.name}
                </span>
                <span className="text-xs text-lime-300">
                    {achievement.points} pts
                </span>
            </p>
            <p className="text-xs text-slate-400">{achievement.goal}</p>
            {achievement.title && (
                <p className="text-xs text-sky-300">
                    Reward title: {achievement.title}
                </p>
            )}
            <StatBar
                label={completed ? 'Done' : 'Progress'}
                value={achievement.progress}
                max={achievement.target}
                color={completed ? 'bg-amber-500' : 'bg-emerald-600'}
                className="h-2.5"
            />
        </li>
    );
}

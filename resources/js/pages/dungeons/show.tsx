import { Head, Link, router, usePage } from '@inertiajs/react';
import { Check, Crown } from 'lucide-react';
import { toast } from 'sonner';
import GameWindow from '@/components/game-window';
import VillageBackdrop from '@/components/village-backdrop';
import { cn } from '@/lib/utils';
import { bag } from '@/routes';
import { fight, leave } from '@/routes/dungeon-runs';
import { enter, index } from '@/routes/dungeons';
import { DIFFICULTY_STYLE } from '@/types/game';
import type { Difficulty } from '@/types/game';

type Wave = {
    name: string;
    is_boss: boolean;
    level: number;
    max_hp: number;
    min_atk: number;
    max_atk: number;
    defense: number;
    exp: number;
    face: string;
};

type Stage = {
    name: string;
    recommended: string;
    reward_exp: number;
    reward_gold: number;
    waves: Wave[];
};

type Run = { id: number; dungeon_id: number; stage: number; wave: number };

type Props = {
    dungeon: {
        id: number;
        name: string;
        difficulty: Difficulty;
        min_level: number;
        max_level: number;
        picture: string;
        reward_exp: number;
        reward_gold: number;
        stages: Stage[];
    };
    runs_left: number;
    /** The ninja's run under way, in this or another dungeon. */
    run: Run | null;
};

function post(url: string) {
    router.post(
        url,
        {},
        {
            preserveScroll: true,
            onError: (errors) =>
                toast.error(errors.dungeon ?? 'That did not work.'),
        },
    );
}

export default function DungeonPage({ dungeon, runs_left, run }: Props) {
    const { character } = usePage().props;
    const here = run?.dungeon_id === dungeon.id ? run : null;
    const tooLow = (character?.level ?? 1) < dungeon.min_level;

    return (
        <>
            <Head title={dungeon.name} />
            <VillageBackdrop>
                <GameWindow title={dungeon.name} closeHref={index().url}>
                    <div className="grid gap-4 md:grid-cols-[240px_1fr]">
                        <aside className="flex flex-col gap-2 text-sm">
                            <img
                                src={dungeon.picture}
                                alt=""
                                className="w-full rounded-md border border-sky-900"
                            />
                            <p>
                                <span
                                    className={cn(
                                        'rounded px-1.5 text-xs font-semibold capitalize',
                                        DIFFICULTY_STYLE[dungeon.difficulty],
                                    )}
                                >
                                    {dungeon.difficulty}
                                </span>{' '}
                                <span className="text-slate-300">
                                    Lv {dungeon.min_level}-{dungeon.max_level}
                                </span>
                            </p>
                            <p className="text-slate-300">
                                Clear reward:{' '}
                                {dungeon.reward_exp.toLocaleString('en-US')} EXP
                                · {dungeon.reward_gold.toLocaleString('en-US')}{' '}
                                gold · gift coupons
                            </p>
                            <p className="text-slate-400">
                                Runs left today: {runs_left}
                            </p>

                            {here ? (
                                <RunControls dungeon={dungeon} run={here} />
                            ) : (
                                <button
                                    type="button"
                                    disabled={tooLow || runs_left === 0}
                                    onClick={() => post(enter(dungeon.id).url)}
                                    className="game-button py-1"
                                >
                                    {tooLow
                                        ? `Needs level ${dungeon.min_level}`
                                        : runs_left === 0
                                          ? 'No runs left today'
                                          : 'Enter'}
                                </button>
                            )}
                            {run && !here && (
                                <p className="text-xs text-red-300">
                                    You are in another dungeon. Finish or leave
                                    it first.
                                </p>
                            )}
                        </aside>

                        <ol className="flex max-h-[62vh] flex-col gap-3 overflow-y-auto pr-1">
                            {dungeon.stages.map((stage, stageIndex) => (
                                <li
                                    key={stage.name}
                                    className="rounded-md border border-sky-900 bg-slate-950/60 p-2"
                                >
                                    <p className="flex justify-between text-sm">
                                        <span className="font-semibold text-amber-200">
                                            {stageIndex + 1}. {stage.name}
                                        </span>
                                        <span className="text-xs text-slate-400">
                                            Lv {stage.recommended} ·{' '}
                                            {stage.reward_gold} gold
                                        </span>
                                    </p>
                                    <div className="mt-2 grid grid-cols-5 gap-2">
                                        {stage.waves.map((wave, waveIndex) => (
                                            <WaveTile
                                                key={waveIndex}
                                                wave={wave}
                                                state={waveState(
                                                    here,
                                                    stageIndex,
                                                    waveIndex,
                                                )}
                                            />
                                        ))}
                                    </div>
                                </li>
                            ))}
                        </ol>
                    </div>
                </GameWindow>
            </VillageBackdrop>
        </>
    );
}

function RunControls({
    dungeon,
    run,
}: {
    dungeon: Props['dungeon'];
    run: Run;
}) {
    return (
        <>
            <p className="text-amber-200">
                Next: {dungeon.stages[run.stage].name}, wave {run.wave + 1}
            </p>
            <button
                type="button"
                onClick={() => post(fight(run.id).url)}
                className="game-button py-1"
            >
                Fight next wave
            </button>
            <div className="flex gap-2">
                <Link
                    href={bag()}
                    className="game-button flex-1 py-0.5 text-center"
                >
                    Inventory
                </Link>
                <button
                    type="button"
                    onClick={() => post(leave(run.id).url)}
                    className="game-button flex-1 py-0.5"
                >
                    Leave
                </button>
            </div>
            <p className="text-xs text-slate-400">
                Health carries over. Drink a potion from the Inventory between
                waves.
            </p>
        </>
    );
}

type WaveState = 'done' | 'next' | 'ahead';

function waveState(run: Run | null, stage: number, wave: number): WaveState {
    if (!run) {
        return 'ahead';
    }

    if (stage < run.stage || (stage === run.stage && wave < run.wave)) {
        return 'done';
    }

    return stage === run.stage && wave === run.wave ? 'next' : 'ahead';
}

function WaveTile({ wave, state }: { wave: Wave; state: WaveState }) {
    return (
        <div
            title={`${wave.name} · Lv ${wave.level} · HP ${wave.max_hp} · Attack ${wave.min_atk}-${wave.max_atk} · Defense ${wave.defense}`}
            className={cn(
                'relative flex flex-col items-center rounded border p-1 text-center',
                state === 'next' &&
                    'border-amber-300 bg-amber-950/40 shadow-[0_0_8px_rgba(252,211,77,0.6)]',
                state === 'done' && 'border-emerald-800 opacity-50',
                state === 'ahead' &&
                    (wave.is_boss ? 'border-red-800' : 'border-sky-950'),
            )}
        >
            {wave.is_boss && (
                <Crown className="absolute top-0.5 right-0.5 size-3.5 text-red-400" />
            )}
            {state === 'done' && (
                <Check className="absolute top-0.5 left-0.5 size-3.5 text-emerald-400" />
            )}
            <img src={wave.face} alt="" className="size-10 object-contain" />
            <span className="line-clamp-1 text-[11px] text-slate-200">
                {wave.name}
            </span>
            <span className="text-[10px] text-slate-400">Lv {wave.level}</span>
        </div>
    );
}

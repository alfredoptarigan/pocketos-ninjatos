import { Head, router, usePage } from '@inertiajs/react';
import { Crown, Lock, Swords } from 'lucide-react';
import { useState } from 'react';
import GameWindow from '@/components/game-window';
import StatBar from '@/components/stat-bar';
import VillageBackdrop from '@/components/village-backdrop';
import type { MonsterArt } from '@/game/battle/types';
import { cn } from '@/lib/utils';
import { bag, village } from '@/routes';
import { fight } from '@/routes/tower';

type Floor = {
    floor: number;
    name: string;
    is_boss: boolean;
    level: number;
    max_hp: number;
    min_atk: number;
    max_atk: number;
    defense: number;
    exp: number;
    art: MonsterArt;
};

type Props = {
    floors: Floor[];
    cleared: number;
    stats: {
        minAttack: number;
        maxAttack: number;
        defense: number;
        dodge: number;
        crit: number;
    };
};

const FLOORS_PER_PAGE = 10;
const LOW_HEALTH_SHARE = 0.3;

export default function Tower({ floors, cleared, stats }: Props) {
    const { character } = usePage().props;
    const next = Math.min(cleared + 1, floors.length);
    const [page, setPage] = useState(Math.floor((next - 1) / FLOORS_PER_PAGE));
    const [selected, setSelected] = useState(next);
    const pages = Math.ceil(floors.length / FLOORS_PER_PAGE);
    const visible = floors.slice(
        page * FLOORS_PER_PAGE,
        (page + 1) * FLOORS_PER_PAGE,
    );
    const target = floors.find((floor) => floor.floor === selected);
    const locked = (floor: number) => floor > cleared + 1;
    const lowHealth = character
        ? character.hp < character.max_hp * LOW_HEALTH_SHARE
        : false;

    return (
        <>
            <Head title="Training Tower" />
            <VillageBackdrop>
                <GameWindow title="Training Tower" closeHref={village().url}>
                    <div className="grid gap-5 md:grid-cols-[220px_1fr]">
                        {character && (
                            <aside className="flex flex-col gap-2 rounded-md border border-amber-900/80 bg-slate-950/60 p-3 text-sm">
                                <p className="font-semibold text-amber-200">
                                    {character.name} · Lv {character.level}
                                </p>
                                <StatBar
                                    label="HP"
                                    value={character.hp}
                                    max={character.max_hp}
                                    color="bg-red-500"
                                />
                                <StatBar
                                    label="CP"
                                    value={character.mp}
                                    max={character.max_mp}
                                    color="bg-sky-500"
                                />
                                <dl className="grid grid-cols-2 gap-x-2 text-slate-300">
                                    <dt>Attack</dt>
                                    <dd>
                                        {stats.minAttack}-{stats.maxAttack}
                                    </dd>
                                    <dt>Defense</dt>
                                    <dd>{stats.defense}</dd>
                                    <dt>Dodge</dt>
                                    <dd>{stats.dodge}%</dd>
                                    <dt>Critical</dt>
                                    <dd>{stats.crit}%</dd>
                                    <dt>Best floor</dt>
                                    <dd>{cleared}</dd>
                                </dl>
                                {lowHealth && (
                                    <p className="text-xs text-red-300">
                                        Low health. Rest a while or{' '}
                                        <a
                                            href={bag().url}
                                            className="underline"
                                        >
                                            drink a potion
                                        </a>
                                        .
                                    </p>
                                )}
                            </aside>
                        )}

                        <div className="flex flex-col gap-3">
                            <div
                                className="flex flex-wrap gap-1"
                                role="tablist"
                                aria-label="Floors"
                            >
                                {Array.from({ length: pages }, (_, index) => (
                                    <button
                                        key={index}
                                        type="button"
                                        role="tab"
                                        aria-selected={index === page}
                                        onClick={() => setPage(index)}
                                        className="game-button px-2 py-0 text-xs"
                                    >
                                        {index * FLOORS_PER_PAGE + 1}-
                                        {Math.min(
                                            floors.length,
                                            (index + 1) * FLOORS_PER_PAGE,
                                        )}
                                    </button>
                                ))}
                            </div>

                            <div
                                role="listbox"
                                aria-label="Tower floors"
                                className="grid grid-cols-5 gap-2"
                            >
                                {visible.map((floor) => (
                                    <button
                                        key={floor.floor}
                                        type="button"
                                        role="option"
                                        aria-selected={floor.floor === selected}
                                        title={`Floor ${floor.floor}: ${floor.name}`}
                                        onClick={() => setSelected(floor.floor)}
                                        className={cn(
                                            'relative flex flex-col items-center rounded-md border-2 bg-slate-950/70 p-1 text-xs',
                                            floor.floor === selected
                                                ? 'border-amber-300 shadow-[0_0_10px_rgba(252,211,77,0.7)]'
                                                : 'border-amber-900/80',
                                            locked(floor.floor) &&
                                                'opacity-50 grayscale',
                                            floor.floor <= cleared &&
                                                'opacity-70',
                                        )}
                                    >
                                        <img
                                            src={floor.art.face}
                                            alt=""
                                            className="size-14 rounded object-cover object-top"
                                        />
                                        <span className="font-semibold">
                                            F{floor.floor}
                                        </span>
                                        {floor.is_boss && (
                                            <Crown
                                                className="absolute top-1 right-1 size-4 text-yellow-300"
                                                aria-label="Boss"
                                            />
                                        )}
                                        {locked(floor.floor) && (
                                            <Lock
                                                className="absolute top-1 left-1 size-3.5"
                                                aria-label="Locked"
                                            />
                                        )}
                                    </button>
                                ))}
                            </div>

                            {target && (
                                <div className="flex flex-wrap items-center gap-4 rounded-md border border-amber-900/80 bg-slate-950/60 p-3">
                                    <img
                                        src={target.art.face}
                                        alt=""
                                        className="size-16 rounded object-cover object-top"
                                    />
                                    <div className="min-w-40 flex-1 text-sm">
                                        <p className="font-semibold text-amber-200">
                                            Floor {target.floor}: {target.name}{' '}
                                            {target.is_boss && '(Boss)'}
                                        </p>
                                        <p className="text-slate-300">
                                            Lv {target.level} · HP{' '}
                                            {target.max_hp} · Atk{' '}
                                            {target.min_atk}-{target.max_atk} ·
                                            Def {target.defense}
                                        </p>
                                        <p className="text-xs text-slate-400">
                                            {target.floor <= cleared
                                                ? 'Cleared — replays give reduced EXP.'
                                                : `${target.exp} EXP on first clear`}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        disabled={locked(target.floor)}
                                        onClick={() =>
                                            router.post(
                                                fight(String(target.floor)).url,
                                            )
                                        }
                                        className="game-button flex items-center gap-2 px-5 py-1.5"
                                    >
                                        <Swords className="size-4" />{' '}
                                        {locked(target.floor)
                                            ? 'Locked'
                                            : 'Fight'}
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </GameWindow>
            </VillageBackdrop>
        </>
    );
}

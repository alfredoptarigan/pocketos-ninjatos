import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import HotspotCanvas from '@/components/hotspot-canvas';
import type { MonsterArt } from '@/game/battle/types';
import { createField, FIELD_HEIGHT, FIELD_WIDTH } from '@/game/field-scene';
import type { Spot } from '@/game/hotspot-scene';
import { cn } from '@/lib/utils';
import { fight, search } from '@/routes/fields';
import { returnMethod as returnToVillage } from '@/routes/village';
import { show as world } from '@/routes/world';

type Monster = {
    id: number;
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

type Search = {
    spot: 'search' | 'cache';
    art: Spot;
    key: { name: string; owned: number } | null;
    readyAt: string | null;
};

type Props = {
    field: { scene: string; name: string; level: number; background: string };
    monsters: Monster[];
    searches: Search[];
};

const SPOT_LABELS: Record<string, string> = {
    search: 'Search',
    cache: 'Hidden cache',
};

/** Seconds until a spot can be searched again, 0 when ready. */
function secondsLeft(search: Search, now: number): number {
    return search.readyAt
        ? Math.max(0, Math.ceil((Date.parse(search.readyAt) - now) / 1000))
        : 0;
}

function useNow(): number {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const timer = setInterval(() => setNow(Date.now()), 1000);

        return () => clearInterval(timer);
    }, []);

    return now;
}

function post(url: string) {
    router.post(
        url,
        {},
        {
            onError: (errors) =>
                toast.error(
                    errors.monster ?? errors.search ?? 'That did not work.',
                ),
        },
    );
}

function hunt(monster: Monster) {
    post(fight(monster.id).url);
}

/** A hunting ground: the original backdrop with its monsters along the top, as in the original. */
export default function Field({ field, monsters, searches }: Props) {
    const now = useNow();

    const searchSpot = (spot: string) => {
        const target = searches.find((s) => s.spot === spot);

        if (!target) {
            return;
        }

        const wait = secondsLeft(target, Date.now());

        if (wait > 0) {
            toast.info(`Nothing new here yet. Come back in ${wait}s.`);
        } else if (target.key && target.key.owned < 1) {
            toast.info(`You need the ${target.key.name} to open this.`);
        } else {
            post(search({ field: field.scene, spot }).url);
        }
    };

    return (
        <>
            <Head title={field.name} />
            <HotspotCanvas
                sceneKey={field.scene}
                width={FIELD_WIDTH}
                height={FIELD_HEIGHT}
                build={(onSpot) =>
                    createField(
                        {
                            background: field.background,
                            spots: Object.fromEntries(
                                searches.map((s) => [s.spot, s.art]),
                            ),
                            monsters,
                        },
                        {
                            spotLabel: (spot) => SPOT_LABELS[spot] ?? spot,
                            onSpot,
                            onMonster: (id) => {
                                const monster = monsters.find(
                                    (m) => m.id === id,
                                );

                                if (monster) {
                                    hunt(monster);
                                }
                            },
                        },
                    )
                }
                onSelect={searchSpot}
                missingHint="Hunting ground assets are missing. Run: python3 tools/extract_world_assets.py ~/Privates/game-pockieninja"
            />

            <ul
                aria-label="Monsters"
                className="absolute top-3 left-1/2 z-10 flex -translate-x-1/2 gap-2"
            >
                {monsters.map((monster) => (
                    <li key={monster.id}>
                        <button
                            type="button"
                            onClick={() => hunt(monster)}
                            title={`Health ${monster.max_hp} · Attack ${monster.min_atk}-${monster.max_atk} · Defense ${monster.defense}`}
                            aria-label={`Fight ${monster.name} (level ${monster.level})`}
                            className="group flex w-24 flex-col items-center"
                        >
                            <span
                                className={cn(
                                    'relative block size-20 overflow-hidden rounded-md border-2 bg-slate-900/80 shadow-lg transition group-hover:scale-105',
                                    monster.is_boss
                                        ? 'border-red-500'
                                        : 'border-slate-600 group-hover:border-amber-300',
                                )}
                            >
                                <img
                                    src={monster.art.face}
                                    alt=""
                                    className="size-full object-cover"
                                />
                                <span className="absolute inset-x-0 bottom-0 bg-black/60 text-center text-sm font-bold text-amber-200">
                                    Lv. {monster.level}
                                </span>
                            </span>
                            <span className="mt-1 w-full truncate rounded border border-amber-700/70 bg-black/70 px-1 text-sm font-bold text-white">
                                {monster.name}
                            </span>
                        </button>
                    </li>
                ))}
            </ul>

            <nav
                aria-label={field.name}
                className="absolute right-3 bottom-16 z-10 flex w-44 flex-col gap-2 rounded-md border border-amber-700/70 bg-slate-950/80 p-2 text-center shadow-xl"
            >
                <p className="font-semibold text-amber-200">{field.name}</p>
                <ul aria-label="Search spots" className="text-left text-xs">
                    {searches.map((s) => {
                        const wait = secondsLeft(s, now);

                        return (
                            <li key={s.spot} className="flex justify-between">
                                <span className="text-slate-300">
                                    {SPOT_LABELS[s.spot]}
                                </span>
                                <span
                                    className={
                                        wait > 0 || (s.key && s.key.owned < 1)
                                            ? 'text-slate-500'
                                            : 'text-lime-300'
                                    }
                                >
                                    {wait > 0
                                        ? `${wait}s`
                                        : s.key
                                          ? `${s.key.owned} key${s.key.owned === 1 ? '' : 's'}`
                                          : 'Ready'}
                                </span>
                            </li>
                        );
                    })}
                </ul>
                <Link
                    href={returnToVillage()}
                    as="button"
                    className="game-button py-1"
                >
                    Village
                </Link>
                <Link href={world()} className="game-button py-1">
                    World Map
                </Link>
            </nav>
        </>
    );
}

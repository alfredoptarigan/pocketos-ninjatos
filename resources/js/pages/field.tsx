import { Head, Link, router } from '@inertiajs/react';
import { toast } from 'sonner';
import type { MonsterArt } from '@/game/battle/types';
import { cn } from '@/lib/utils';
import { village } from '@/routes';
import { fight } from '@/routes/fields';
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

type Props = {
    field: { scene: string; name: string; level: number; background: string };
    monsters: Monster[];
};

function hunt(monster: Monster) {
    router.post(
        fight(monster.id).url,
        {},
        {
            onError: (errors) =>
                toast.error(errors.monster ?? 'You cannot fight right now.'),
        },
    );
}

/** A hunting ground: the original backdrop with its monsters along the top, as in the original. */
export default function Field({ field, monsters }: Props) {
    return (
        <>
            <Head title={field.name} />
            <img
                src={field.background}
                alt=""
                className="absolute inset-0 size-full object-cover"
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
                <Link href={village()} className="game-button py-1">
                    Village
                </Link>
                <Link href={world()} className="game-button py-1">
                    World Map
                </Link>
            </nav>
        </>
    );
}

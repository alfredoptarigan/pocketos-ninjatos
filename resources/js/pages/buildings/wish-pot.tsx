import { Head, Link, router, usePage } from '@inertiajs/react';
import { Ticket } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import CharacterSprite from '@/components/character-sprite';
import GameWindow from '@/components/game-window';
import VillageBackdrop from '@/components/village-backdrop';
import { cn } from '@/lib/utils';
import { village } from '@/routes';
import { index as wardrobe } from '@/routes/outfits';
import { draw } from '@/routes/wish-pot';
import { characterAssets, RARITY_BORDER, RARITY_TEXT } from '@/types/game';
import type { Outfit, Rarity } from '@/types/game';

type Choice = Outfit & { owned: boolean };

type Pot = {
    key: string;
    name: string;
    price: number;
    /** Random pots: rarity => weight. */
    odds?: Partial<Record<Rarity, number>>;
    /** Pick pots: the outfits the ninja may choose from. */
    choices: Choice[] | null;
};

type Drawn = { outfit: Outfit; duplicate: boolean; gold: number };

function oddsText(odds: NonNullable<Pot['odds']>): string {
    const total = Object.values(odds).reduce((sum, weight) => sum + weight, 0);

    return Object.entries(odds)
        .map(
            ([rarity, weight]) =>
                `${rarity} ${Math.round((weight * 100) / total)}%`,
        )
        .join(' · ');
}

export default function WishPot({ pots }: { pots: Pot[] }) {
    const { character } = usePage().props;
    const [drawn, setDrawn] = useState<Drawn | null>(null);
    const [opening, setOpening] = useState<string | null>(null);

    useEffect(
        () =>
            router.on('flash', (event) => {
                const flash = (event as CustomEvent).detail?.flash;

                if (flash?.drawn) {
                    setDrawn(flash.drawn as Drawn);
                }
            }),
        [],
    );

    const open = (pot: Pot, outfit?: string) =>
        router.post(draw(pot.key).url, outfit ? { outfit } : {}, {
            preserveScroll: true,
            onStart: () => setOpening(pot.key),
            onFinish: () => setOpening(null),
            onError: (errors) =>
                toast.error(
                    errors.coupons ??
                        errors.outfit ??
                        'The pot would not open.',
                ),
        });

    return (
        <>
            <Head title="Lucky Pot" />
            <VillageBackdrop>
                <GameWindow title="Lucky Pot" closeHref={village().url}>
                    <div className="grid gap-6 md:grid-cols-[220px_1fr]">
                        <Reveal drawn={drawn} />

                        <div className="flex flex-col gap-3">
                            <ul
                                aria-label="Wishing Pots"
                                className="flex max-h-[55vh] flex-col gap-2 overflow-y-auto pr-1"
                            >
                                {pots.map((pot) => (
                                    <PotRow
                                        key={pot.key}
                                        pot={pot}
                                        opening={opening}
                                        canAfford={
                                            (character?.coupons ?? 0) >=
                                            pot.price
                                        }
                                        onOpen={(outfit) => open(pot, outfit)}
                                    />
                                ))}
                            </ul>

                            <div className="flex items-center justify-between text-sm">
                                <Link
                                    href={wardrobe()}
                                    className="game-button px-3 py-0.5"
                                >
                                    Wardrobe
                                </Link>
                                <p className="flex items-center gap-2 font-semibold text-rose-300">
                                    <Ticket className="size-4" />
                                    {character?.coupons.toLocaleString(
                                        'en-US',
                                    )}{' '}
                                    gift coupons
                                </p>
                            </div>
                            <p className="text-xs text-slate-400">
                                Earn gift coupons from the daily sign-in
                                (Gifts), new Training Tower floors and dungeon
                                clears. A duplicate from a random pot pays out
                                gold instead.
                            </p>
                        </div>
                    </div>
                </GameWindow>
            </VillageBackdrop>
        </>
    );
}

type PotRowProps = {
    pot: Pot;
    opening: string | null;
    canAfford: boolean;
    onOpen: (outfit?: string) => void;
};

/** One pot: its odds, or for pick pots the outfits to choose from. */
function PotRow({ pot, opening, canAfford, onOpen }: PotRowProps) {
    const [choice, setChoice] = useState<string | null>(null);
    const picking = pot.choices !== null;
    const allOwned = picking && pot.choices!.every((c) => c.owned);

    return (
        <li className="flex flex-wrap items-center gap-3 rounded-md border border-amber-900/80 bg-slate-950/60 p-3">
            <div className="min-w-40 flex-1">
                <p className="font-semibold text-amber-200">{pot.name}</p>
                {pot.odds ? (
                    <p className="text-xs text-slate-400 capitalize">
                        {oddsText(pot.odds)}
                    </p>
                ) : (
                    <p className="text-xs text-slate-400">
                        Pick the outfit you want.
                    </p>
                )}
            </div>
            <span className="flex items-center gap-1 text-sm text-rose-300">
                <Ticket className="size-4" />
                {pot.price}
            </span>
            <button
                type="button"
                onClick={() => onOpen(choice ?? undefined)}
                disabled={
                    opening !== null ||
                    !canAfford ||
                    (picking && choice === null)
                }
                className="game-button min-w-20 px-4 py-1"
            >
                {opening === pot.key
                    ? 'Opening…'
                    : allOwned
                      ? 'All owned'
                      : 'Open'}
            </button>
            {picking && (
                <div
                    role="radiogroup"
                    aria-label={`${pot.name} outfits`}
                    className="flex w-full flex-wrap gap-2"
                >
                    {pot.choices!.map((outfit) => (
                        <button
                            key={outfit.key}
                            type="button"
                            role="radio"
                            aria-checked={choice === outfit.key}
                            disabled={outfit.owned}
                            title={
                                outfit.owned
                                    ? `${outfit.name} (owned)`
                                    : outfit.name
                            }
                            onClick={() => setChoice(outfit.key)}
                            className={cn(
                                'flex w-20 flex-col items-center rounded border-2 bg-slate-900/80 p-1 text-[11px]',
                                choice === outfit.key
                                    ? 'border-amber-300 shadow-[0_0_8px_rgba(252,211,77,0.6)]'
                                    : RARITY_BORDER[outfit.rarity],
                                outfit.owned && 'opacity-40',
                            )}
                        >
                            <img
                                src={characterAssets(outfit.key).face}
                                alt=""
                                className="size-10 object-contain"
                            />
                            <span className="line-clamp-1">{outfit.name}</span>
                            {outfit.owned && (
                                <span className="text-slate-400">Owned</span>
                            )}
                        </button>
                    ))}
                    {pot.choices!.length === 0 && (
                        <p className="text-xs text-slate-400">
                            Nothing in this pot fits your ninja.
                        </p>
                    )}
                </div>
            )}
        </li>
    );
}

function Reveal({ drawn }: { drawn: Drawn | null }) {
    if (!drawn) {
        return (
            <div className="flex h-[260px] items-center justify-center rounded-md border border-dashed border-amber-900/80 bg-slate-950/40 p-4 text-center text-sm text-slate-400">
                Open a pot to call a new outfit.
            </div>
        );
    }

    const { outfit } = drawn;

    return (
        <div
            aria-live="polite"
            className={cn(
                'flex flex-col items-center rounded-md border-2 bg-slate-950/60 p-2',
                RARITY_BORDER[outfit.rarity],
            )}
        >
            <CharacterSprite
                key={outfit.key}
                avatar={outfit.key}
                className="h-[200px] w-full"
            />
            <p className={cn('font-semibold', RARITY_TEXT[outfit.rarity])}>
                {outfit.name}
            </p>
            <p className="text-xs text-slate-300 capitalize">
                {outfit.rarity} · +{outfit.bonus}% stats
            </p>
            {drawn.duplicate && (
                <p className="text-xs text-amber-300">
                    Already owned: +{drawn.gold.toLocaleString('en-US')} gold
                </p>
            )}
        </div>
    );
}

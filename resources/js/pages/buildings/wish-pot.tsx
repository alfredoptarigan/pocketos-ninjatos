import { Head, Link, router, usePage } from '@inertiajs/react';
import { Lock, Ticket } from 'lucide-react';
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

type TitleChoice = { id: number; code: string; name: string; owned: boolean };

/** config('game.outfits.pots'), as WishPotController sends it. */
type Pot = {
    key: string;
    name: string;
    price?: number;
    /** Random pots: rarity => weight. */
    odds?: Partial<Record<Rarity, number>>;
    /** Outfits come out already upgraded to +level. */
    level?: number;
    /** The feature a locked pot waits for. */
    locked?: string;
    /** Pick pots let the ninja choose; pool pots draw from `choices`. */
    pickable: boolean;
    choices: Choice[] | null;
    titles: TitleChoice[] | null;
};

type Drawn =
    | { outfit: Outfit; duplicate: boolean; shards: number }
    | { title: { name: string } };

const TABS = ['Outfits', 'Upgraded', 'Magic', 'Titles', 'Locked'] as const;
type Tab = (typeof TABS)[number];

function tabOf(pot: Pot): Tab {
    if (pot.locked) {
        return 'Locked';
    }

    if (pot.titles) {
        return 'Titles';
    }

    if (pot.pickable) {
        return 'Magic';
    }

    return pot.level ? 'Upgraded' : 'Outfits';
}

/** Owned at the pot's level already: nothing more to gain from picking it. */
function maxedOut(outfit: Choice, pot: Pot): boolean {
    return outfit.owned && outfit.level >= (pot.level ?? 0);
}

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
    const [tab, setTab] = useState<Tab>('Outfits');

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
                        errors.pot ??
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
                            <div
                                role="tablist"
                                aria-label="Pot kinds"
                                className="flex flex-wrap gap-1"
                            >
                                {TABS.map((name) => (
                                    <button
                                        key={name}
                                        type="button"
                                        role="tab"
                                        aria-selected={tab === name}
                                        onClick={() => setTab(name)}
                                        className={cn(
                                            'game-button px-3 py-0.5 text-sm',
                                            tab !== name && 'opacity-60',
                                        )}
                                    >
                                        {name}
                                    </button>
                                ))}
                            </div>
                            <ul
                                aria-label="Wishing Pots"
                                className="flex max-h-[55vh] flex-col gap-2 overflow-y-auto pr-1"
                            >
                                {pots
                                    .filter((pot) => tabOf(pot) === tab)
                                    .map((pot) => (
                                        <PotRow
                                            key={pot.key}
                                            pot={pot}
                                            opening={opening}
                                            canAfford={
                                                (character?.coupons ?? 0) >=
                                                (pot.price ?? 0)
                                            }
                                            onOpen={(outfit) =>
                                                open(pot, outfit)
                                            }
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
                                clears. An outfit you already own at that level
                                gives outfit shards for upgrades instead;
                                Upgraded and +27 pots raise owned outfits.
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

/** One pot: its odds or contents, the outfits to pick from, or what it waits for. */
function PotRow({ pot, opening, canAfford, onOpen }: PotRowProps) {
    const [choice, setChoice] = useState<string | null>(null);

    if (pot.locked) {
        return (
            <li className="flex items-center gap-3 rounded-md border border-slate-700 bg-slate-950/60 p-3 opacity-70">
                <Lock className="size-5 text-slate-400" />
                <div className="flex-1">
                    <p className="font-semibold text-slate-300">{pot.name}</p>
                    <p className="text-xs text-slate-400">{pot.locked}</p>
                </div>
            </li>
        );
    }

    const soldOut = pot.titles
        ? pot.titles.every((title) => title.owned)
        : pot.pickable && pot.choices!.every((c) => maxedOut(c, pot));

    return (
        <li className="flex flex-wrap items-center gap-3 rounded-md border border-amber-900/80 bg-slate-950/60 p-3">
            <div className="min-w-40 flex-1">
                <p className="font-semibold text-amber-200">{pot.name}</p>
                <p className="text-xs text-slate-400 capitalize">
                    {pot.odds
                        ? oddsText(pot.odds)
                        : pot.titles
                          ? 'One title you do not own yet'
                          : pot.pickable
                            ? 'Pick the outfit you want'
                            : 'One of these outfits'}
                    {pot.level ? ` · comes at +${pot.level}` : ''}
                </p>
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
                    soldOut ||
                    (pot.pickable && choice === null)
                }
                className="game-button min-w-20 px-4 py-1"
            >
                {opening === pot.key
                    ? 'Opening…'
                    : soldOut
                      ? 'All owned'
                      : 'Open'}
            </button>
            {pot.choices && (
                <div
                    role={pot.pickable ? 'radiogroup' : 'list'}
                    aria-label={`${pot.name} outfits`}
                    className="flex w-full flex-wrap gap-2"
                >
                    {pot.choices.map((outfit) => (
                        <OutfitChoice
                            key={outfit.key}
                            outfit={outfit}
                            pickable={pot.pickable}
                            chosen={choice === outfit.key}
                            maxed={maxedOut(outfit, pot)}
                            onChoose={() => setChoice(outfit.key)}
                        />
                    ))}
                    {pot.choices.length === 0 && (
                        <p className="text-xs text-slate-400">
                            Nothing in this pot fits your ninja.
                        </p>
                    )}
                </div>
            )}
            {pot.titles && (
                <ul className="flex w-full flex-wrap gap-1 text-xs">
                    {pot.titles.map((title) => (
                        <li
                            key={title.code}
                            className={cn(
                                'rounded border border-amber-800 px-2 py-0.5 text-amber-100',
                                title.owned && 'opacity-40',
                            )}
                        >
                            {title.name}
                            {title.owned && ' (owned)'}
                        </li>
                    ))}
                </ul>
            )}
        </li>
    );
}

type OutfitChoiceProps = {
    outfit: Choice;
    pickable: boolean;
    chosen: boolean;
    /** Owned at this pot's level: cannot be picked. */
    maxed: boolean;
    onChoose: () => void;
};

function OutfitChoice({
    outfit,
    pickable,
    chosen,
    maxed,
    onChoose,
}: OutfitChoiceProps) {
    const body = (
        <>
            <img
                src={characterAssets(outfit.key).face}
                alt=""
                className="size-10 object-contain"
            />
            <span className="line-clamp-1">{outfit.name}</span>
            {outfit.owned && (
                <span className="text-slate-400">Owned +{outfit.level}</span>
            )}
        </>
    );
    const style = cn(
        'flex w-20 flex-col items-center rounded border-2 bg-slate-900/80 p-1 text-[11px]',
        chosen
            ? 'border-amber-300 shadow-[0_0_8px_rgba(252,211,77,0.6)]'
            : RARITY_BORDER[outfit.rarity],
        maxed && 'opacity-40',
    );

    if (!pickable) {
        return (
            <div role="listitem" className={style} title={outfit.name}>
                {body}
            </div>
        );
    }

    return (
        <button
            type="button"
            role="radio"
            aria-checked={chosen}
            disabled={maxed}
            title={outfit.name}
            onClick={onChoose}
            className={style}
        >
            {body}
        </button>
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

    if ('title' in drawn) {
        return (
            <div
                aria-live="polite"
                className="flex h-[260px] flex-col items-center justify-center gap-2 rounded-md border-2 border-amber-400 bg-slate-950/60 p-4 text-center"
            >
                <p className="text-xs text-slate-300">New title</p>
                <p className="text-lg font-semibold text-amber-200">
                    {drawn.title.name}
                </p>
                <p className="text-xs text-slate-400">
                    Wear it from the Inventory title plate.
                </p>
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
                {outfit.rarity}
                {outfit.level > 0 && ` +${outfit.level}`} · +{outfit.bonus}%
                stats
            </p>
            {drawn.duplicate && (
                <p className="text-xs text-amber-300">
                    Already owned: +{drawn.shards} outfit shards
                </p>
            )}
        </div>
    );
}

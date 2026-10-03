import { Head, Link, router, usePage } from '@inertiajs/react';
import CharacterSprite from '@/components/character-sprite';
import GameWindow from '@/components/game-window';
import VillageBackdrop from '@/components/village-backdrop';
import { cn } from '@/lib/utils';
import { bag as inventory, village } from '@/routes';
import { takeOff, wear } from '@/routes/outfits';
import { show as wishPot } from '@/routes/wish-pot';
import { characterAssets, RARITY_BORDER, RARITY_TEXT } from '@/types/game';
import type { Outfit } from '@/types/game';

type Props = {
    outfits: Outfit[];
    /** Id of the worn outfit; null wears the created avatar. */
    worn: number | null;
    /** The created avatar's asset key. */
    avatar: string;
};

const send = (url: string) => router.post(url, {}, { preserveScroll: true });

export default function Wardrobe({ outfits, worn, avatar }: Props) {
    const { character } = usePage().props;
    const current = outfits.find((outfit) => outfit.id === worn);

    return (
        <>
            <Head title="Wardrobe" />
            <VillageBackdrop>
                <GameWindow title="Wardrobe" closeHref={village().url}>
                    <div className="grid gap-6 md:grid-cols-[200px_1fr]">
                        <div className="flex flex-col items-center gap-1">
                            {character && (
                                <CharacterSprite
                                    key={character.avatar}
                                    avatar={character.avatar}
                                    className="h-[220px] w-full"
                                />
                            )}
                            <p
                                className={cn(
                                    'font-semibold',
                                    current
                                        ? RARITY_TEXT[current.rarity]
                                        : 'text-amber-200',
                                )}
                            >
                                {current?.name ?? 'Own look'}
                            </p>
                            {current && (
                                <p className="text-xs text-slate-300">
                                    +{current.bonus}% health, attack and defense
                                </p>
                            )}
                            <Link
                                href={inventory()}
                                className="game-button mt-2 px-3 py-0.5 text-sm"
                            >
                                Inventory
                            </Link>
                        </div>

                        <div
                            role="listbox"
                            aria-label="Owned outfits"
                            className="grid grid-cols-3 content-start gap-2 sm:grid-cols-5 lg:grid-cols-6"
                        >
                            <OutfitTile
                                face={characterAssets(avatar).face}
                                name="Own look"
                                selected={worn === null}
                                className="border-amber-700"
                                onSelect={() => send(takeOff().url)}
                            />
                            {outfits.map((outfit) => (
                                <OutfitTile
                                    key={outfit.id}
                                    face={characterAssets(outfit.key).face}
                                    name={outfit.name}
                                    selected={outfit.id === worn}
                                    className={cn(RARITY_BORDER[outfit.rarity])}
                                    nameClass={RARITY_TEXT[outfit.rarity]}
                                    onSelect={() => send(wear(outfit.id).url)}
                                />
                            ))}
                            {outfits.length === 0 && (
                                <p className="col-span-full text-sm text-slate-400">
                                    No outfits yet. Open a{' '}
                                    <Link
                                        href={wishPot()}
                                        className="text-amber-300 underline"
                                    >
                                        Wishing Pot
                                    </Link>{' '}
                                    at the Lucky Pot in Waterfall Village.
                                </p>
                            )}
                        </div>
                    </div>
                </GameWindow>
            </VillageBackdrop>
        </>
    );
}

type TileProps = {
    face: string;
    name: string;
    selected: boolean;
    className: string;
    nameClass?: string;
    onSelect: () => void;
};

function OutfitTile({
    face,
    name,
    selected,
    className,
    nameClass,
    onSelect,
}: TileProps) {
    return (
        <button
            type="button"
            role="option"
            aria-selected={selected}
            title={selected ? `${name} (worn)` : `Wear ${name}`}
            onClick={selected ? undefined : onSelect}
            className={cn(
                'flex flex-col items-center gap-1 rounded-md border-2 bg-slate-950/70 p-1 transition',
                className,
                selected
                    ? 'ring-2 ring-amber-300'
                    : 'opacity-80 hover:opacity-100',
            )}
        >
            <img src={face} alt="" className="size-14 object-contain" />
            <span
                className={cn(
                    'line-clamp-2 text-center text-xs',
                    nameClass ?? 'text-amber-200',
                )}
            >
                {name}
            </span>
        </button>
    );
}

import { Head, Link, router, usePage } from '@inertiajs/react';
import GameWindow from '@/components/game-window';
import VillageBackdrop from '@/components/village-backdrop';
import { cn } from '@/lib/utils';
import { village } from '@/routes';
import { record } from '@/routes/collection';
import { index as wardrobe } from '@/routes/outfits';
import { characterAssets, RARITY_BORDER, RARITY_TEXT } from '@/types/game';
import type { Outfit, Rarity } from '@/types/game';

type CollectibleOutfit = Outfit & {
    attributes: { strength: number; agility: number; stamina: number };
    owned: boolean;
    recorded: boolean;
};

type TierProgress = { recorded: number; tier: number; percent: number };

type Tier = [number, number, number, string | null];

type Props = {
    outfits: CollectibleOutfit[];
    tiers: Record<Rarity, TierProgress>;
    rules: {
        record_level: number;
        /** [outfits recorded, character level, percent, title code] per tier. */
        tiers: Record<Rarity, Tier[]>;
    };
};

const RARITIES: Rarity[] = ['orange', 'blue', 'grey'];

export default function Collection({ outfits, tiers, rules }: Props) {
    const { errors } = usePage().props;
    const error = Object.values(errors ?? {})[0];

    return (
        <>
            <Head title="Avatar Collection" />
            <VillageBackdrop>
                <GameWindow title="Avatar Collection" closeHref={village().url}>
                    <div className="flex flex-col gap-4">
                        <p className="text-xs text-slate-300">
                            Record an outfit upgraded to +{rules.record_level}{' '}
                            to gain its attributes for good; it stays in your
                            wardrobe. Every 5 recorded outfits of a colour raise
                            health and attack.{' '}
                            <Link
                                href={wardrobe()}
                                className="text-amber-300 underline"
                            >
                                Wardrobe
                            </Link>
                        </p>

                        <div className="grid gap-2 sm:grid-cols-3">
                            {RARITIES.map((rarity) => (
                                <TierCard
                                    key={rarity}
                                    rarity={rarity}
                                    progress={tiers[rarity]}
                                    levels={rules.tiers[rarity]}
                                />
                            ))}
                        </div>

                        {error && (
                            <p role="alert" className="text-sm text-red-300">
                                {error}
                            </p>
                        )}

                        <ul className="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-6">
                            {outfits.map((outfit) => (
                                <CollectionTile
                                    key={outfit.id}
                                    outfit={outfit}
                                    recordLevel={rules.record_level}
                                />
                            ))}
                        </ul>
                    </div>
                </GameWindow>
            </VillageBackdrop>
        </>
    );
}

function TierCard({
    rarity,
    progress,
    levels,
}: {
    rarity: Rarity;
    progress: TierProgress;
    levels: Tier[];
}) {
    const next = levels[progress.tier];

    return (
        <div
            className={cn(
                'rounded-md border-2 bg-slate-950/70 p-2 text-xs',
                RARITY_BORDER[rarity],
            )}
        >
            <p className={cn('font-semibold capitalize', RARITY_TEXT[rarity])}>
                {rarity}: {progress.recorded} recorded, tier {progress.tier}
            </p>
            <p className="text-slate-300">
                +{progress.percent}% health and attack
            </p>
            {next && (
                <p className="text-slate-400">
                    Next: {next[0]} recorded at level {next[1]} for +{next[2]}%
                </p>
            )}
        </div>
    );
}

function CollectionTile({
    outfit,
    recordLevel,
}: {
    outfit: CollectibleOutfit;
    recordLevel: number;
}) {
    const { strength, agility, stamina } = outfit.attributes;
    const canRecord =
        outfit.owned && !outfit.recorded && outfit.level >= recordLevel;

    return (
        <li
            className={cn(
                'flex flex-col items-center gap-1 rounded-md border-2 bg-slate-950/70 p-1 text-center text-xs',
                RARITY_BORDER[outfit.rarity],
                !outfit.owned && 'opacity-40 grayscale',
            )}
        >
            <img
                src={characterAssets(outfit.key).face}
                alt=""
                className="size-14 object-contain"
            />
            <span className={cn('line-clamp-2', RARITY_TEXT[outfit.rarity])}>
                {outfit.name}
                {outfit.owned && ` +${outfit.level}`}
            </span>
            <span className="text-slate-400">
                Str {strength} · Agi {agility} · Sta {stamina}
            </span>
            {outfit.recorded ? (
                <span className="text-emerald-300">Recorded</span>
            ) : (
                <button
                    type="button"
                    className="game-button px-2 py-0.5"
                    disabled={!canRecord}
                    title={
                        outfit.owned ? `Needs +${recordLevel}` : 'Not owned yet'
                    }
                    onClick={() =>
                        router.post(
                            record(outfit.id).url,
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    Record
                </button>
            )}
        </li>
    );
}

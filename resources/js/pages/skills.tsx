import { Head, router, usePage } from '@inertiajs/react';
import { Check, Coins, Lock } from 'lucide-react';
import GameWindow from '@/components/game-window';
import VillageBackdrop from '@/components/village-backdrop';
import { cn } from '@/lib/utils';
import { village } from '@/routes';
import { learn } from '@/routes/skills';

type Jutsu = {
    id: string;
    name: string;
    school: string;
    kind: string;
    description: string;
    level: number;
    requires: string | null;
    price: number;
    icon: string;
    learned: boolean;
};

const KIND_LABELS: Record<string, string> = {
    strike: 'Attack jutsu',
    follow_up: 'Follow-up',
    extra: 'Before acting',
    counter: 'Counter',
    block: 'Defense',
    reflect: 'Reflect',
    heal: 'Healing',
    revive: 'Revival',
};

export default function Skills({ skills }: { skills: Jutsu[] }) {
    const { character } = usePage().props;
    const byId = Object.fromEntries(skills.map((skill) => [skill.id, skill]));
    const schools = [...new Set(skills.map((skill) => skill.school))];

    // Why a jutsu cannot be learned yet, or null when it can.
    const blocker = (skill: Jutsu): string | null => {
        if (!character) {
            return 'No ninja';
        }

        if (character.level < skill.level) {
            return `Needs level ${skill.level}`;
        }

        if (skill.requires && !byId[skill.requires]?.learned) {
            return `Learn ${byId[skill.requires]?.name ?? 'the previous jutsu'} first`;
        }

        if (character.gold < skill.price) {
            return `Needs ${skill.price} gold`;
        }

        return null;
    };

    return (
        <>
            <Head title="Jutsu" />
            <VillageBackdrop>
                <GameWindow title="Jutsu" closeHref={village().url}>
                    <p className="mb-3 flex items-center justify-between text-sm text-slate-300">
                        <span>
                            Learned jutsu fire on their own in battle when their
                            chance comes up and you have the chakra.
                        </span>
                        <span className="flex items-center gap-1 font-semibold text-amber-300">
                            <Coins className="size-4" />
                            {character?.gold.toLocaleString('en-US')} gold
                        </span>
                    </p>
                    <div className="grid max-h-[60vh] gap-4 overflow-y-auto pr-1 md:grid-cols-2">
                        {schools.map((school) => (
                            <section
                                key={school}
                                aria-label={school}
                                className="flex flex-col gap-2"
                            >
                                <h2 className="font-semibold text-amber-200">
                                    {school}
                                </h2>
                                {skills
                                    .filter((skill) => skill.school === school)
                                    .map((skill) => {
                                        const reason = skill.learned
                                            ? null
                                            : blocker(skill);

                                        return (
                                            <article
                                                key={skill.id}
                                                className={cn(
                                                    'flex gap-3 rounded-md border bg-slate-950/60 p-2',
                                                    skill.learned
                                                        ? 'border-amber-500/80'
                                                        : 'border-amber-900/70',
                                                )}
                                            >
                                                <img
                                                    src={skill.icon}
                                                    alt=""
                                                    className={cn(
                                                        'size-12 shrink-0 rounded',
                                                        !skill.learned &&
                                                            reason &&
                                                            'opacity-50 grayscale',
                                                    )}
                                                />
                                                <div className="min-w-0 flex-1 text-sm">
                                                    <p className="flex items-center gap-2 font-semibold text-slate-100">
                                                        {skill.name}
                                                        <span className="text-xs font-normal text-sky-300">
                                                            {KIND_LABELS[
                                                                skill.kind
                                                            ] ?? skill.kind}
                                                        </span>
                                                    </p>
                                                    <p className="text-xs text-slate-300">
                                                        {skill.description}
                                                    </p>
                                                    <p className="mt-1 text-xs text-slate-400">
                                                        Lv {skill.level} ·{' '}
                                                        {skill.price} gold
                                                        {skill.requires &&
                                                            ` · after ${byId[skill.requires]?.name}`}
                                                    </p>
                                                </div>
                                                <div className="flex shrink-0 flex-col items-end justify-center gap-1">
                                                    {skill.learned ? (
                                                        <span className="flex items-center gap-1 text-xs font-semibold text-emerald-300">
                                                            <Check className="size-4" />{' '}
                                                            Learned
                                                        </span>
                                                    ) : (
                                                        <>
                                                            <button
                                                                type="button"
                                                                disabled={
                                                                    reason !==
                                                                    null
                                                                }
                                                                onClick={() =>
                                                                    router.post(
                                                                        learn(
                                                                            skill.id,
                                                                        ).url,
                                                                        {},
                                                                        {
                                                                            preserveScroll: true,
                                                                        },
                                                                    )
                                                                }
                                                                className="game-button px-3 py-0.5 text-sm"
                                                            >
                                                                Learn
                                                            </button>
                                                            {reason && (
                                                                <span className="flex items-center gap-1 text-[11px] text-slate-400">
                                                                    <Lock className="size-3" />{' '}
                                                                    {reason}
                                                                </span>
                                                            )}
                                                        </>
                                                    )}
                                                </div>
                                            </article>
                                        );
                                    })}
                            </section>
                        ))}
                    </div>
                </GameWindow>
            </VillageBackdrop>
        </>
    );
}

import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Lock } from 'lucide-react';
import type { DragEvent } from 'react';
import {
    BuySlotDialog,
    InfoDialog,
    ResetDialog,
    ScrollSkillDialog,
} from '@/components/skill-dialogs';
import SkillCell from '@/components/skill-cell';
import SkillMenu from '@/components/skill-menu';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import VillageBackdrop from '@/components/village-backdrop';
import { DRAG_TYPE, send, upgradeLabel } from '@/lib/skills';
import type { Jutsu, Passive, SkillRules } from '@/lib/skills';
import { cn } from '@/lib/utils';
import { village } from '@/routes';
import { equip, page as switchPage, unequip } from '@/routes/skills';

type UltimateInfo = {
    id: string;
    name: string;
    description: string;
    icon: string;
    upgraded: boolean;
    /** Percent chance against an opponent without an upgraded outfit. */
    chance: number;
    upgradedFrom: number;
};

type Props = {
    passives: Passive[];
    /** Tier by tier, schools left to right: ten per row. */
    skills: Jutsu[];
    points: number;
    /** Equipped jutsu ids per page, by slot. */
    pages: (string | null)[][];
    page: number;
    openSlots: number;
    slotPrices: number[];
    slotsBought: number;
    rules: SkillRules;
    ultimate: UltimateInfo;
};

const TOTAL_SLOTS = 10;

export default function Skills({
    passives,
    skills,
    points,
    pages,
    page,
    openSlots,
    slotPrices,
    slotsBought,
    rules,
    ultimate,
}: Props) {
    const { errors } = usePage().props;
    const error = Object.values(errors ?? {})[0];
    const byId = Object.fromEntries(skills.map((skill) => [skill.id, skill]));
    const equipped = pages[page] ?? [];
    const freeSlot =
        Array.from({ length: openSlots }, (_, slot) => slot).find(
            (slot) => !equipped[slot],
        ) ?? null;
    const learnedSchools = new Set(
        skills.filter((skill) => skill.level > 0).map((skill) => skill.school),
    );
    const nextPrice = slotPrices[slotsBought];

    const drop = (slot: number) => (event: DragEvent) => {
        const id = event.dataTransfer.getData(DRAG_TYPE);

        if (id) {
            event.preventDefault();
            send(equip().url, { skill: id, slot });
        }
    };

    return (
        <>
            <Head title="Skills" />
            <VillageBackdrop>
                <section
                    aria-label="Skills"
                    className="skill-window relative mx-auto w-full max-w-[960px] px-3 pt-9 pb-4 sm:px-5"
                >
                    <h1 className="skill-label absolute -top-4 left-1/2 -translate-x-1/2 px-6 py-1 text-lg font-bold">
                        Skills
                    </h1>
                    <Link
                        href={village().url}
                        aria-label="Close"
                        className="game-close absolute -top-3 -right-3 block size-[22px]"
                    />

                    <div className="skill-panel overflow-x-auto p-3">
                        <div className="grid min-w-[560px] gap-3">
                            <div className="grid grid-cols-[1fr_auto_1fr] items-center">
                                <InfoDialog points={points} rules={rules}>
                                    <button
                                        type="button"
                                        className="skill-button justify-self-start px-4 py-0.5 text-lg"
                                    >
                                        Info
                                    </button>
                                </InfoDialog>
                                <h2 className="skill-label px-12 py-0.5">
                                    Passive
                                </h2>
                                <ResetDialog coupons={rules.resetCoupons}>
                                    <button
                                        type="button"
                                        className="skill-button justify-self-end px-4 py-0.5 text-lg"
                                    >
                                        Reset
                                    </button>
                                </ResetDialog>
                            </div>
                            <ul
                                className="grid grid-cols-10 justify-items-center gap-y-2"
                                aria-label="Passive skills"
                            >
                                {passives.map((passive) => (
                                    <li key={passive.id}>
                                        <SkillCell
                                            icon={passive.icon}
                                            name={`${passive.name} (level ${passive.level}): strengthens this school's jutsu`}
                                            faded={
                                                !learnedSchools.has(
                                                    passive.school,
                                                )
                                            }
                                            bar={`Lv ${passive.level}`}
                                        />
                                    </li>
                                ))}
                            </ul>

                            <div className="grid grid-cols-[1fr_auto_1fr] items-center">
                                <span />
                                <h2 className="skill-label px-12 py-0.5">
                                    Active
                                </h2>
                                <p className="justify-self-end text-sm font-semibold text-sky-100">
                                    Skill points: {points}
                                </p>
                            </div>
                            <ul
                                className="grid grid-cols-10 justify-items-center gap-y-2"
                                aria-label="Active skills"
                            >
                                {skills.map((jutsu) => {
                                    const previous = jutsu.requires
                                        ? byId[jutsu.requires]
                                        : null;

                                    return (
                                        <li key={jutsu.id}>
                                            <SkillCell
                                                icon={jutsu.icon}
                                                name={jutsu.name}
                                                faded={jutsu.level === 0}
                                                bar={upgradeLabel(jutsu.level)}
                                                dragId={
                                                    jutsu.level > 0
                                                        ? jutsu.id
                                                        : undefined
                                                }
                                                dragType={DRAG_TYPE}
                                            >
                                                {(icon) => (
                                                    <SkillMenu
                                                        jutsu={jutsu}
                                                        missing={
                                                            previous &&
                                                            previous.level === 0
                                                                ? previous.name
                                                                : null
                                                        }
                                                        points={points}
                                                        rules={rules}
                                                        freeSlot={freeSlot}
                                                    >
                                                        {icon}
                                                    </SkillMenu>
                                                )}
                                            </SkillCell>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    </div>

                    <div className="skill-panel mt-3 overflow-x-auto p-3">
                        <div className="grid min-w-[560px] gap-3">
                            <div className="grid grid-cols-[1fr_auto_1fr] items-center">
                                <ScrollSkillDialog>
                                    <button
                                        type="button"
                                        className="skill-button justify-self-start px-3 py-0.5 text-lg"
                                    >
                                        Scroll Skill
                                    </button>
                                </ScrollSkillDialog>
                                <h2 className="skill-label px-12 py-0.5">
                                    Equipped
                                </h2>
                                <span />
                            </div>
                            <ol
                                className="grid grid-cols-10 justify-items-center gap-y-2"
                                aria-label={`Equipped page ${page + 1}`}
                            >
                                {Array.from(
                                    { length: TOTAL_SLOTS },
                                    (_, slot) => (
                                        <li key={slot}>
                                            <Slot
                                                slot={slot}
                                                jutsu={
                                                    equipped[slot]
                                                        ? byId[equipped[slot]]
                                                        : undefined
                                                }
                                                open={slot < openSlots}
                                                price={
                                                    slot === openSlots
                                                        ? nextPrice
                                                        : undefined
                                                }
                                                onDrop={drop(slot)}
                                            />
                                        </li>
                                    ),
                                )}
                            </ol>
                            <nav
                                className="flex items-center justify-center gap-4 text-lg font-bold text-white"
                                aria-label="Equipped pages"
                            >
                                <button
                                    type="button"
                                    aria-label="Previous page"
                                    disabled={page === 0}
                                    onClick={() =>
                                        send(switchPage().url, {
                                            page: page - 1,
                                        })
                                    }
                                    className="text-sky-200 disabled:opacity-40"
                                >
                                    <ChevronLeft className="size-7" />
                                </button>
                                <span>
                                    {page + 1}/{pages.length}
                                </span>
                                <button
                                    type="button"
                                    aria-label="Next page"
                                    disabled={page === pages.length - 1}
                                    onClick={() =>
                                        send(switchPage().url, {
                                            page: page + 1,
                                        })
                                    }
                                    className="text-amber-300 disabled:opacity-40"
                                >
                                    <ChevronRight className="size-7" />
                                </button>
                            </nav>
                        </div>
                    </div>

                    <UltimatePanel ultimate={ultimate} />

                    {error && (
                        <p
                            role="alert"
                            className="mt-2 text-center text-sm text-red-200"
                        >
                            {error}
                        </p>
                    )}
                </section>
            </VillageBackdrop>
        </>
    );
}

type SlotProps = {
    slot: number;
    jutsu: Jutsu | undefined;
    open: boolean;
    /** Gift coupons to open this slot, when it is the next one for sale. */
    price: number | undefined;
    onDrop: (event: DragEvent) => void;
};

function Slot({ slot, jutsu, open, price, onDrop }: SlotProps) {
    if (!open) {
        const lock = (
            <span
                className={cn(
                    'flex size-11 items-center justify-center rounded-md border-2 sm:size-12',
                    price !== undefined
                        ? 'border-amber-700 bg-amber-400 text-amber-950'
                        : 'border-slate-500 bg-slate-400 text-slate-600',
                )}
            >
                <Lock className="size-6" />
            </span>
        );

        return price !== undefined ? (
            <div className="flex flex-col items-center gap-1">
                <BuySlotDialog price={price}>
                    <button
                        type="button"
                        aria-label={`Open slot ${slot + 1} for ${price} gift coupons`}
                    >
                        {lock}
                    </button>
                </BuySlotDialog>
                <span className="skill-bar flex h-4 w-11 items-center justify-center text-[11px] sm:w-12">
                    {price}
                </span>
            </div>
        ) : (
            <div
                className="flex flex-col items-center gap-1"
                aria-label={`Slot ${slot + 1} locked`}
            >
                {lock}
                <span className="skill-bar h-4 w-11 sm:w-12" />
            </div>
        );
    }

    return (
        <div onDragOver={(event) => event.preventDefault()} onDrop={onDrop}>
            {jutsu ? (
                <SkillCell
                    icon={jutsu.icon}
                    name={jutsu.name}
                    bar={upgradeLabel(jutsu.level)}
                >
                    {(icon) => (
                        <DropdownMenu>
                            <DropdownMenuTrigger className="rounded-sm focus-visible:ring-2 focus-visible:ring-sky-300 focus-visible:outline-none">
                                {icon}
                            </DropdownMenuTrigger>
                            <DropdownMenuContent>
                                <DropdownMenuLabel>
                                    {slot + 1}. {jutsu.name}{' '}
                                    {upgradeLabel(jutsu.level)}
                                </DropdownMenuLabel>
                                <DropdownMenuItem
                                    onSelect={() =>
                                        send(unequip().url, { slot })
                                    }
                                >
                                    Unequip
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </SkillCell>
            ) : (
                <div
                    className="flex flex-col items-center gap-1"
                    aria-label={`Slot ${slot + 1} empty`}
                >
                    <span className="size-11 rounded-sm bg-[#16323f] shadow-inner sm:size-12" />
                    <span className="skill-bar h-4 w-11 sm:w-12" />
                </div>
            )}
        </div>
    );
}

/** The worn outfit's ultimate: always ready, nothing to learn or equip. */
function UltimatePanel({ ultimate }: { ultimate: UltimateInfo }) {
    return (
        <div
            aria-label="Ultimate"
            className="mx-auto mt-3 flex max-w-xl items-center gap-3 rounded border border-yellow-500/60 bg-slate-950/70 p-2 text-sm"
        >
            <img
                src={ultimate.icon}
                alt=""
                className="size-11 rounded border border-yellow-400"
            />
            <div className="min-w-0">
                <p className="font-semibold text-yellow-300">
                    Ultimate: {ultimate.name}
                    {ultimate.upgraded && (
                        <span className="ml-2 text-xs text-amber-200">
                            Upgraded cinematic
                        </span>
                    )}
                </p>
                <p className="text-xs text-slate-200">{ultimate.description}</p>
                <p className="text-xs text-slate-400">
                    {ultimate.chance}% chance on a low opponent · comes with the
                    outfit you wear · stronger cinematic from +
                    {ultimate.upgradedFrom}
                </p>
            </div>
        </div>
    );
}

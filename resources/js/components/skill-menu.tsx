import type { ReactNode } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { KIND_LABELS, send, upgradeLabel } from '@/lib/skills';
import type { Jutsu, SkillRules } from '@/lib/skills';
import { equip, learn } from '@/routes/skills';

type Props = {
    jutsu: Jutsu;
    /** Name of the previous jutsu when it is not learned yet. */
    missing: string | null;
    points: number;
    rules: SkillRules;
    /** First empty open slot of the page in use, or null. */
    freeSlot: number | null;
    children: ReactNode;
};

/** Click menu of a jutsu icon: what it does, learn/upgrade, equip. */
export default function SkillMenu({
    jutsu,
    missing,
    points,
    rules,
    freeSlot,
    children,
}: Props) {
    const learned = jutsu.level > 0;
    const maxed = jutsu.level >= rules.maxLevel;
    const blocked = missing
        ? `Learn ${missing} first`
        : points < 1
          ? 'No skill points left'
          : null;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger className="rounded-sm focus-visible:ring-2 focus-visible:ring-sky-300 focus-visible:outline-none">
                {children}
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-72">
                <DropdownMenuLabel>
                    {jutsu.name} {upgradeLabel(jutsu.level)}
                    <span className="block text-xs font-normal text-muted-foreground">
                        {KIND_LABELS[jutsu.kind] ?? jutsu.kind} · {jutsu.chance}
                        % chance
                        {jutsu.chakra > 0 && ` · ${jutsu.chakra} chakra`}
                    </span>
                </DropdownMenuLabel>
                <p className="px-2 pb-2 text-xs">{jutsu.description}</p>
                {jutsu.level > 1 && (
                    <p className="px-2 pb-2 text-xs text-muted-foreground">
                        Upgrades: +{(jutsu.level - 1) * rules.upgrade.chance}%
                        chance, +
                        {(jutsu.level - 1) * rules.upgrade.power_percent}%
                        power.
                    </p>
                )}
                <DropdownMenuSeparator />
                {!learned && (
                    <DropdownMenuItem
                        disabled={blocked !== null}
                        onSelect={() => send(learn(jutsu.id).url)}
                    >
                        {blocked ?? 'Learn (1 skill point)'}
                    </DropdownMenuItem>
                )}
                {learned && !maxed && (
                    <DropdownMenuItem
                        disabled={points < 1}
                        onSelect={() => send(learn(jutsu.id).url)}
                    >
                        {points < 1
                            ? 'No skill points left'
                            : `Upgrade to +${jutsu.level} (1 skill point)`}
                    </DropdownMenuItem>
                )}
                {learned && (
                    <DropdownMenuItem
                        disabled={freeSlot === null}
                        onSelect={() =>
                            freeSlot !== null &&
                            send(equip().url, {
                                skill: jutsu.id,
                                slot: freeSlot,
                            })
                        }
                    >
                        {freeSlot === null ? 'Equip (no free slot)' : 'Equip'}
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

import type { ReactNode } from 'react';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { send } from '@/lib/skills';
import type { SkillRules } from '@/lib/skills';
import { reset, slots } from '@/routes/skills';

// The original's Scroll Skills came with a later client; the backup has no data for them.
const SCROLL_SKILLS = [
    {
        name: 'Amaterasu',
        text: '200% damage; black flames that cannot be put out.',
    },
    {
        name: 'Water Colliding Wave',
        text: '250% damage; may ignore a quarter of the defense.',
    },
    {
        name: 'Izanagi',
        text: 'Illusions hit harder and all damage taken is halved.',
    },
];

function Confirm({
    title,
    text,
    action,
    onConfirm,
    children,
}: {
    title: string;
    text: ReactNode;
    action: string;
    onConfirm: () => void;
    children: ReactNode;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{text}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose
                        className="skill-button px-4 py-1"
                        onClick={onConfirm}
                    >
                        {action}
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export function InfoDialog({
    points,
    rules,
    children,
}: {
    points: number;
    rules: SkillRules;
    children: ReactNode;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Skills</DialogTitle>
                    <DialogDescription>
                        You have {points} skill points left.
                    </DialogDescription>
                </DialogHeader>
                <ul className="list-disc space-y-1 pl-5 text-sm">
                    <li>
                        Every level up gives one skill point: learn a new jutsu,
                        or upgrade one up to +{rules.maxLevel - 1}.
                    </li>
                    <li>
                        Each column is a school; learn its jutsu from top to
                        bottom.
                    </li>
                    <li>
                        Each upgrade adds {rules.upgrade.chance}% chance and{' '}
                        {rules.upgrade.power_percent}% power.
                    </li>
                    <li>
                        Passives grow by themselves at levels{' '}
                        {rules.passive.levels.join(', ')}: +
                        {rules.passive.chance}% chance and +
                        {rules.passive.power_percent}% power to every jutsu per
                        level.
                    </li>
                    <li>
                        Only equipped jutsu fight, in slot order. Switch between
                        three pages with the arrows.
                    </li>
                </ul>
            </DialogContent>
        </Dialog>
    );
}

export function ResetDialog({
    coupons,
    children,
}: {
    coupons: number;
    children: ReactNode;
}) {
    return (
        <Confirm
            title="Reset skills"
            text={`Forget every jutsu and get all skill points back for ${coupons} gift coupons?`}
            action="Reset"
            onConfirm={() => send(reset().url)}
        >
            {children}
        </Confirm>
    );
}

export function BuySlotDialog({
    price,
    children,
}: {
    price: number;
    children: ReactNode;
}) {
    return (
        <Confirm
            title="Open a slot"
            text={`Open one more equipped slot on every page for ${price} gift coupons?`}
            action="Open"
            onConfirm={() => send(slots().url)}
        >
            {children}
        </Confirm>
    );
}

export function ScrollSkillDialog({ children }: { children: ReactNode }) {
    return (
        <Dialog>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Scroll Skills</DialogTitle>
                    <DialogDescription>
                        Learned from rare scrolls in a later version of the
                        original game. They are not part of this server yet.
                    </DialogDescription>
                </DialogHeader>
                <ul className="space-y-2 text-sm">
                    {SCROLL_SKILLS.map((skill) => (
                        <li
                            key={skill.name}
                            className="rounded border p-2 opacity-70"
                        >
                            <p className="font-semibold">
                                {skill.name} (locked)
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {skill.text}
                            </p>
                        </li>
                    ))}
                </ul>
            </DialogContent>
        </Dialog>
    );
}

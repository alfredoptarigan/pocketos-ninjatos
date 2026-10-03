import { Head, router } from '@inertiajs/react';
import { Check, Ticket } from 'lucide-react';
import { toast } from 'sonner';
import GameWindow from '@/components/game-window';
import VillageBackdrop from '@/components/village-backdrop';
import { cn } from '@/lib/utils';
import { village } from '@/routes';
import { signIn } from '@/routes/gifts';

type Props = {
    /** Days of the current streak already claimed (0 when it lapsed). */
    streak: number;
    claimedToday: boolean;
    rewards: { day: number; coupons: number }[];
    /** EXP every sign-in pays at the ninja's level. */
    exp: number;
};

export default function Gifts({ streak, claimedToday, rewards, exp }: Props) {
    // The day today's sign-in counts as: the next one, or day 1 after a full week.
    const today = claimedToday ? streak : (streak % rewards.length) + 1;
    const claim = () =>
        router.post(
            signIn().url,
            {},
            {
                preserveScroll: true,
                onError: (errors) =>
                    toast.error(errors.sign_in ?? 'That did not work.'),
            },
        );

    return (
        <>
            <Head title="Gifts" />
            <VillageBackdrop>
                <GameWindow
                    title="Daily Sign-in"
                    closeHref={village().url}
                    className="max-w-2xl"
                >
                    <p className="mb-3 text-center text-sm text-slate-300">
                        Sign in every day for +{exp.toLocaleString('en-US')} EXP
                        and gift coupons. Miss a day and the week starts over.
                    </p>
                    <ol className="grid grid-cols-4 gap-2 sm:grid-cols-7">
                        {rewards.map(({ day, coupons }) => {
                            const done =
                                day < today || (claimedToday && day === today);
                            const isToday = day === today && !claimedToday;

                            return (
                                <li
                                    key={day}
                                    className={cn(
                                        'relative flex flex-col items-center gap-1 rounded-md border-2 bg-slate-950/70 p-2 text-center',
                                        isToday &&
                                            'border-amber-300 shadow-[0_0_10px_rgba(252,211,77,0.6)]',
                                        done && 'border-emerald-700 opacity-60',
                                        !isToday && !done && 'border-sky-900',
                                    )}
                                >
                                    {done && (
                                        <Check className="absolute top-1 right-1 size-4 text-emerald-400" />
                                    )}
                                    <span className="text-xs text-slate-400">
                                        Day {day}
                                    </span>
                                    <Ticket className="size-6 text-rose-300" />
                                    <span className="text-sm font-semibold text-rose-200">
                                        ×{coupons}
                                    </span>
                                </li>
                            );
                        })}
                    </ol>
                    <div className="mt-4 flex justify-center">
                        <button
                            type="button"
                            disabled={claimedToday}
                            onClick={claim}
                            className="game-button px-6 py-1 text-lg"
                        >
                            {claimedToday
                                ? 'Come back tomorrow'
                                : `Sign in (day ${today})`}
                        </button>
                    </div>
                </GameWindow>
            </VillageBackdrop>
        </>
    );
}

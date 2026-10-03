import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    /** Where the close (X) button leads, usually back to the village. */
    closeHref?: string;
    children: ReactNode;
    /** Overrides the default width, e.g. "max-w-xl" for narrow panels. */
    className?: string;
};

/** A window drawn with the original client's gold-trimmed frame and jewel ornament. */
export default function GameWindow({
    title,
    closeHref,
    children,
    className,
}: Props) {
    return (
        <section
            aria-label={title}
            className={cn(
                'game-window relative w-full max-w-5xl text-slate-100',
                className,
            )}
        >
            <img
                src="/game-assets/ui/frame-ornament.png"
                alt=""
                className="pointer-events-none absolute -top-[34px] left-1/2 h-[34px] w-[204px] -translate-x-1/2"
            />
            {closeHref && (
                <Link
                    href={closeHref}
                    aria-label="Close"
                    className="game-close absolute -top-[22px] -right-[18px] block size-[22px]"
                />
            )}
            <h1 className="mb-4 text-center text-xl font-bold tracking-wide text-amber-300 drop-shadow">
                {title}
            </h1>
            {children}
        </section>
    );
}

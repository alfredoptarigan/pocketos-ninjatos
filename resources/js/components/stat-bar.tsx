import { cn } from '@/lib/utils';

type Props = {
    label: string;
    value: number;
    max: number;
    /** Tailwind background class for the filled part. */
    color: string;
    className?: string;
};

export default function StatBar({
    label,
    value,
    max,
    color,
    className,
}: Props) {
    const percent = Math.round((Math.max(0, value) / Math.max(1, max)) * 100);

    return (
        <div
            role="meter"
            aria-label={label}
            aria-valuemin={0}
            aria-valuemax={max}
            aria-valuenow={value}
            className={cn(
                'relative h-3.5 overflow-hidden rounded-full bg-black/60 ring-1 ring-white/20',
                className,
            )}
        >
            <div
                className={cn('h-full rounded-full transition-[width]', color)}
                style={{ width: `${percent}%` }}
            />
            <span className="absolute inset-0 flex items-center justify-center text-[10px] leading-none font-bold text-white [text-shadow:0_0_2px_#000]">
                {label} {value}/{max}
            </span>
        </div>
    );
}

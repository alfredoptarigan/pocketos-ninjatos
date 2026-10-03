import type { DragEvent, ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    icon: string;
    name: string;
    /** Grey icon for jutsu not learned yet. */
    faded?: boolean;
    /** Text in the dark bar under the icon. */
    bar?: string;
    /** Set to make the icon draggable onto an equipped slot. */
    dragId?: string;
    dragType?: string;
    /** Wraps the icon, e.g. in a dropdown menu trigger. */
    children?: (icon: ReactNode) => ReactNode;
};

/** One skill icon with the level bar underneath, as in the original panel. */
export default function SkillCell({
    icon,
    name,
    faded,
    bar,
    dragId,
    dragType,
    children,
}: Props) {
    const onDragStart = (event: DragEvent) => {
        if (dragId && dragType) {
            event.dataTransfer.setData(dragType, dragId);
        }
    };
    const image = (
        <img
            src={icon}
            alt={name}
            title={name}
            draggable={Boolean(dragId)}
            onDragStart={onDragStart}
            className={cn(
                'size-11 rounded-sm border border-slate-900 bg-slate-950 object-cover shadow sm:size-12',
                faded && 'brightness-75 grayscale',
            )}
        />
    );

    return (
        <div className="flex flex-col items-center gap-1">
            {children ? children(image) : image}
            <span className="skill-bar flex h-4 w-11 items-center justify-center text-[11px] leading-none font-bold sm:w-12">
                {bar}
            </span>
        </div>
    );
}

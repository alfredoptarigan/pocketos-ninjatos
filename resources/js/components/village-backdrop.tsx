import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

// Players without a character yet (the create screen) see the home village.
const FALLBACK_VILLAGE = '111';

/** Building and menu windows open over the dimmed village the player is in. */
export default function VillageBackdrop({ children }: { children: ReactNode }) {
    const { character } = usePage().props;

    return (
        <div
            className="absolute inset-0 overflow-y-auto bg-cover bg-center"
            style={{
                backgroundImage: `url(/game-assets/villages/${character?.village ?? FALLBACK_VILLAGE}.jpg)`,
            }}
        >
            <div className="flex min-h-full items-center justify-center bg-black/55 px-4 pt-24 pb-20">
                {children}
            </div>
        </div>
    );
}

import { Link } from '@inertiajs/react';
import { toast } from 'sonner';
import { bag } from '@/routes';

type MenuButton = { key: string; label: string; href?: string };

// The original bottom bar, in its original order.
const BUTTONS: MenuButton[] = [
    { key: 'bag', label: 'Bag', href: bag().url },
    { key: 'character', label: 'Character' },
    { key: 'tools', label: 'Ninja Tools' },
    { key: 'forge', label: 'Forge' },
    { key: 'friends', label: 'Friends' },
    { key: 'missions', label: 'Missions' },
    { key: 'pet', label: 'Pet' },
    { key: 'gifts', label: 'Gifts' },
];

const icon = (key: string, state: 'up' | 'over' | 'down') =>
    `/game-assets/ui/menu/${key}-${state}.png`;

function MenuIcon({ button }: { button: MenuButton }) {
    return (
        <>
            <img
                src={icon(button.key, 'up')}
                alt=""
                className="block group-hover:hidden group-active:hidden"
            />
            <img
                src={icon(button.key, 'over')}
                alt=""
                className="hidden group-hover:block group-active:hidden"
            />
            <img
                src={icon(button.key, 'down')}
                alt=""
                className="hidden group-active:block"
            />
        </>
    );
}

/** The original bottom-right menu bar; unbuilt features say so instead of doing nothing. */
export default function GameMenu() {
    return (
        <nav
            aria-label="Game menu"
            className="flex gap-1 rounded-md border border-slate-500/70 bg-gradient-to-b from-slate-600/90 to-slate-800/90 p-1 shadow-lg"
        >
            {BUTTONS.map((button) =>
                button.href ? (
                    <Link
                        key={button.key}
                        href={button.href}
                        title={button.label}
                        aria-label={button.label}
                        className="group"
                    >
                        <MenuIcon button={button} />
                    </Link>
                ) : (
                    <button
                        key={button.key}
                        type="button"
                        title={`${button.label} (coming soon)`}
                        aria-label={`${button.label} (coming soon)`}
                        onClick={() =>
                            toast.info(`${button.label} is coming soon.`)
                        }
                        className="group"
                    >
                        <MenuIcon button={button} />
                    </button>
                ),
            )}
        </nav>
    );
}

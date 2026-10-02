import { Link, router, usePage } from '@inertiajs/react';
import { LogOut, Settings } from 'lucide-react';
import type { ReactNode } from 'react';
import BackgroundMusic from '@/components/background-music';
import GameMenu from '@/components/game-menu';
import PlayerHud from '@/components/player-hud';
import { villageMusic } from '@/game/music';
import { logout } from '@/routes';
import { edit as settings } from '@/routes/profile';

const TOWER_PAGES = ['tower', 'battle'];
const TOWER_MUSIC = '/game-assets/music/singlegate.mp3';

/**
 * Full-screen game shell: the page fills the screen, with the player HUD,
 * the original bottom menu and background music layered on top. Used as a
 * persistent layout so the music survives page visits.
 */
export default function GameLayout({ children }: { children: ReactNode }) {
    const { character } = usePage().props;
    const { component } = usePage();
    // The battle screen hides the HUD so it does not spoil the outcome.
    const showHud = character && component !== 'battle';
    const music = TOWER_PAGES.includes(component)
        ? TOWER_MUSIC
        : villageMusic(character?.village);

    return (
        <div className="dark relative h-dvh w-full overflow-hidden bg-black text-foreground">
            <main className="absolute inset-0">{children}</main>

            {character && (
                <div className="pointer-events-none absolute inset-x-3 top-3 z-20 flex items-start justify-between">
                    <div className="pointer-events-auto">
                        {showHud && <PlayerHud character={character} />}
                    </div>
                    <div className="pointer-events-auto flex gap-2">
                        <BackgroundMusic src={music} />
                        <Link
                            href={settings()}
                            aria-label="Settings"
                            className="game-button grid size-9 place-items-center"
                        >
                            <Settings className="size-4" />
                        </Link>
                        <Link
                            href={logout()}
                            as="button"
                            onClick={() => router.flushAll()}
                            aria-label="Log out"
                            data-test="logout-button"
                            className="game-button grid size-9 place-items-center"
                        >
                            <LogOut className="size-4" />
                        </Link>
                    </div>
                </div>
            )}

            {showHud && (
                <div className="absolute right-3 bottom-3 z-20">
                    <GameMenu />
                </div>
            )}
        </div>
    );
}

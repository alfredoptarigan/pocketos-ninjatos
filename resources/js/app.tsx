import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import GameLayout from '@/layouts/game-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Pages (or page folders) that render inside the full-screen game shell.
const GAME_PAGES = [
    'village',
    'inventory',
    'dungeons',
    'gifts',
    'buildings',
    'character',
    'tower',
    'battle',
    'skills',
    'world',
    'field',
];

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            case GAME_PAGES.some(
                (page) => name === page || name.startsWith(`${page}/`),
            ):
                return GameLayout;
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    defaults: {
        // In game every page shows "/" (like the original client), so moving
        // around replaces the history entry instead of stacking "/" copies.
        visitOptions: (_href, options) =>
            window.location.pathname === '/'
                ? { ...options, replace: true }
                : options,
    },
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

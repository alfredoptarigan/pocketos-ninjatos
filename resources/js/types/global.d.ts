import type { Auth } from '@/types/auth';
import type { Character } from '@/types/game';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            character: Character | null;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}

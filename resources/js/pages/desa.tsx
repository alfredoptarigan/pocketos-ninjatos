import { Head, usePage } from '@inertiajs/react';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { desa } from '@/routes';

export default function Desa() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Desa" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Selamat datang, {auth.user.name}!
                    </h1>
                    <p className="text-muted-foreground">
                        Desa ninjamu sedang dibangun. Nantikan petualangan
                        berikutnya.
                    </p>
                </div>
                <div className="relative min-h-[60vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                </div>
            </div>
        </>
    );
}

Desa.layout = {
    breadcrumbs: [
        {
            title: 'Desa',
            href: desa(),
        },
    ],
};

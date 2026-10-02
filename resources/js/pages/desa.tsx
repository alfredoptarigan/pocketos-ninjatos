import { Head, usePage } from '@inertiajs/react';
import VillageCanvas from '@/components/village-canvas';
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
                        Klik di mana saja pada peta untuk menggerakkan ninjamu.
                    </p>
                </div>
                <div className="relative min-h-[60vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <VillageCanvas playerName={auth.user.name} />
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

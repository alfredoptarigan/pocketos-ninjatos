import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import VillageCanvas from '@/components/village-canvas';
import { buildingName } from '@/game/village-scene';
import { desa } from '@/routes';

// Main city of the original game; other villages come with travel later.
const HOME_VILLAGE_ID = '111';

export default function Desa() {
    const { auth } = usePage().props;
    const [selected, setSelected] = useState<string | null>(null);

    return (
        <>
            <Head title="Desa" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Selamat datang, {auth.user.name}!
                    </h1>
                    <p className="text-muted-foreground">
                        {selected
                            ? `${buildingName(selected)} segera hadir.`
                            : 'Klik bangunan di desa untuk mengunjunginya.'}
                    </p>
                </div>
                <div className="relative aspect-video w-full overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <VillageCanvas
                        villageId={HOME_VILLAGE_ID}
                        onBuildingSelect={setSelected}
                    />
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

import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import PlayerHud from '@/components/player-hud';
import VillageCanvas from '@/components/village-canvas';
import { buildingName } from '@/game/village-scene';
import { desa } from '@/routes';
import { show as apotek } from '@/routes/apotek';

// Main city of the original game; other villages come with travel later.
const HOME_VILLAGE_ID = '111';

// Buildings that already have a screen; the rest show "segera hadir".
const BUILDING_ROUTES: Record<string, () => { url: string }> = {
    pharmacy: apotek,
};

export default function Desa() {
    const { character } = usePage().props;
    const [selected, setSelected] = useState<string | null>(null);

    const visitBuilding = (key: string) => {
        const route = BUILDING_ROUTES[key];

        if (route) {
            router.visit(route().url);
        } else {
            setSelected(key);
        }
    };

    return (
        <>
            <Head title="Desa" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Selamat datang, {character?.name}!
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
                        onBuildingSelect={visitBuilding}
                    />
                    {character && <PlayerHud character={character} />}
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

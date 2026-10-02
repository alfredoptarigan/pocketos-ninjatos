import { Head, router, usePage } from '@inertiajs/react';
import { MapPin } from 'lucide-react';
import { useState } from 'react';
import PlayerHud from '@/components/player-hud';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import VillageCanvas from '@/components/village-canvas';
import { buildingName } from '@/game/village-scene';
import { village as villageRoute } from '@/routes';
import { show as pharmacy } from '@/routes/pharmacy';
import { travel } from '@/routes/village';

type VillageSummary = { id: string; name: string };

type Props = {
    village: VillageSummary;
    villages: VillageSummary[];
};

// Buildings that already have a screen; the rest show "coming soon".
const BUILDING_ROUTES: Record<string, () => { url: string }> = {
    salve: pharmacy,
};

export default function Village({ village, villages }: Props) {
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

    const travelTo = (destination: string) => {
        setSelected(null);
        router.post(travel().url, { village: destination });
    };

    return (
        <>
            <Head title={village.name} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            {village.name}
                        </h1>
                        <p className="text-muted-foreground">
                            {selected
                                ? `${buildingName(selected)} is coming soon.`
                                : `Welcome, ${character?.name}! Click a building to visit it.`}
                        </p>
                    </div>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline">
                                <MapPin />
                                Travel
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-56">
                            <DropdownMenuLabel>Travel to</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            {villages.map((destination) => (
                                <DropdownMenuItem
                                    key={destination.id}
                                    disabled={destination.id === village.id}
                                    onSelect={() => travelTo(destination.id)}
                                >
                                    <img
                                        src={`/game-assets/villages/${destination.id}.jpg`}
                                        alt=""
                                        className="h-8 w-14 rounded object-cover"
                                    />
                                    {destination.name}
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
                <div className="relative aspect-video w-full overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <VillageCanvas
                        villageId={village.id}
                        onBuildingSelect={visitBuilding}
                    />
                    {character && <PlayerHud character={character} />}
                </div>
            </div>
        </>
    );
}

Village.layout = {
    breadcrumbs: [
        {
            title: 'Village',
            href: villageRoute(),
        },
    ],
};

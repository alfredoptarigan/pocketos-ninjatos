import { Head, Link, router } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { toast } from 'sonner';
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
import { show as equipmentShop } from '@/routes/equipment-shop';
import { show as pharmacy } from '@/routes/pharmacy';
import { show as tower } from '@/routes/tower';
import { travel } from '@/routes/village';
import { show as world } from '@/routes/world';

type VillageSummary = { id: string; name: string };

type Props = {
    village: VillageSummary;
    villages: VillageSummary[];
};

// Buildings that already have a screen; the rest say "coming soon".
const BUILDING_ROUTES: Record<string, () => { url: string }> = {
    salve: pharmacy,
    equip: equipmentShop,
    // The original single-gate tower is entered from the hall.
    hall: tower,
};

export default function Village({ village, villages }: Props) {
    const visitBuilding = (key: string) => {
        const route = BUILDING_ROUTES[key];

        if (route) {
            router.visit(route().url);
        } else {
            toast.info(`${buildingName(key)} is coming soon.`);
        }
    };

    return (
        <>
            <Head title={village.name} />
            <VillageCanvas
                villageId={village.id}
                onBuildingSelect={visitBuilding}
            />

            <div className="absolute top-3 left-1/2 z-10 flex -translate-x-1/2 gap-2">
                <Link href={world()} className="game-button px-4 py-1 text-lg">
                    World Map
                </Link>
                <DropdownMenu>
                    <DropdownMenuTrigger className="game-button flex items-center gap-1 px-4 py-1 text-lg">
                        {village.name}
                        <ChevronDown className="size-4" />
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="center" className="w-56">
                        <DropdownMenuLabel>Travel to</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        {villages.map((destination) => (
                            <DropdownMenuItem
                                key={destination.id}
                                disabled={destination.id === village.id}
                                onSelect={() =>
                                    router.post(travel().url, {
                                        village: destination.id,
                                    })
                                }
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
        </>
    );
}

import { Head, Link, router, usePage } from '@inertiajs/react';
import { Assets } from 'pixi.js';
import { useState } from 'react';
import { toast } from 'sonner';
import HotspotCanvas from '@/components/hotspot-canvas';
import { createHotspotScene } from '@/game/hotspot-scene';
import type { HotspotData } from '@/game/hotspot-scene';
import { village as villageRoute } from '@/routes';
import { show as showField } from '@/routes/fields';
import { travel } from '@/routes/village';

type Area = {
    scene: string;
    name: string;
    village: string | null;
    level: number;
    monsters: { name: string; level: number; is_boss: boolean }[];
};

type Props = {
    fields: Area[];
    villages: { id: string; name: string }[];
};

// Written by tools/extract_world_assets.py; the map is 768x426 like the original.
const WORLD_URL = '/game-assets/world.json';
const MAP_WIDTH = 768;
const MAP_HEIGHT = 426;
const LABEL_SIZE = 13;

export default function World({ fields, villages }: Props) {
    const { character } = usePage().props;
    const [hovered, setHovered] = useState<string | null>(null);
    const fieldOf = (key: string) => fields.find((f) => f.scene === key);
    const villageOf = (key: string) => villages.find((v) => v.id === key);
    const nameOf = (key: string) =>
        fieldOf(key)?.name ?? villageOf(key)?.name ?? 'Not open yet';

    const select = (key: string) => {
        const field = fieldOf(key);

        if (villageOf(key)) {
            router.post(travel().url, { village: key });
        } else if (!field) {
            toast.info(`${nameOf(key)}.`);
        } else if ((character?.level ?? 0) < field.level) {
            toast.error(`${field.name} needs level ${field.level}.`);
        } else {
            router.visit(showField(field.scene).url);
        }
    };

    const build = async (
        onSelect: (key: string) => void,
        onHover: (key: string | null) => void,
    ) =>
        createHotspotScene(await Assets.load<HotspotData>(WORLD_URL), {
            label: nameOf,
            labelSize: LABEL_SIZE,
            onSelect,
            onHover,
        });

    const field = hovered ? fieldOf(hovered) : undefined;
    const owner = field?.village ? villageOf(field.village)?.name : null;

    return (
        <>
            <Head title="World Map" />
            <HotspotCanvas
                sceneKey="world"
                width={MAP_WIDTH}
                height={MAP_HEIGHT}
                build={build}
                onSelect={select}
                onHover={setHovered}
                missingHint="World map assets are missing. Run: python3 tools/extract_world_assets.py ~/Privates/game-pockieninja"
            />

            <div className="absolute top-3 left-1/2 z-10 -translate-x-1/2">
                <Link
                    href={villageRoute()}
                    className="game-button px-4 py-1 text-lg"
                >
                    Back to village
                </Link>
            </div>

            {field && (
                <aside
                    aria-label={`${field.name} details`}
                    className="pointer-events-none absolute bottom-16 left-3 z-10 w-64 rounded-md border border-amber-700/70 bg-slate-950/90 p-3 text-sm text-slate-200 shadow-xl"
                >
                    <p className="font-semibold text-amber-200">{field.name}</p>
                    <p
                        className={
                            (character?.level ?? 0) < field.level
                                ? 'text-red-400'
                                : 'text-slate-300'
                        }
                    >
                        Entry level {field.level}
                        {owner && ` · ${owner} grounds`}
                    </p>
                    <ul className="mt-1">
                        {field.monsters.map((monster) => (
                            <li
                                key={monster.name}
                                className={
                                    monster.is_boss
                                        ? 'text-red-400'
                                        : 'text-lime-300'
                                }
                            >
                                Lv {monster.level} {monster.name}
                                {monster.is_boss && ' (boss)'}
                            </li>
                        ))}
                    </ul>
                </aside>
            )}
        </>
    );
}

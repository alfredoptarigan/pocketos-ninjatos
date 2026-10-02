import HotspotCanvas from '@/components/hotspot-canvas';
import { createVillage, WORLD_HEIGHT, WORLD_WIDTH } from '@/game/village-scene';

type Props = {
    villageId: string;
    onBuildingSelect: (key: string) => void;
};

export default function VillageCanvas({ villageId, onBuildingSelect }: Props) {
    return (
        <HotspotCanvas
            sceneKey={villageId}
            width={WORLD_WIDTH}
            height={WORLD_HEIGHT}
            build={(onSelect) => createVillage(villageId, onSelect)}
            onSelect={onBuildingSelect}
            missingHint="Village assets are missing. Run: python3 tools/extract_village_assets.py ~/Privates/game-pockieninja"
        />
    );
}

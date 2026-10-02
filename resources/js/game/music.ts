const MUSIC_URL = '/game-assets/music';

// The backup's scene-music config is empty, so villages share the four
// original main-city themes; the dark Shadow Village gets its own track.
const VILLAGE_TRACKS: Record<string, string> = {
    '111': 'maincity1',
    '121': 'maincity2',
    '131': 'maincity3',
    '141': 'maincity4',
    '151': 'maincity1',
    '161': 'maincity2',
    '171': 'blackcity',
};

const DEFAULT_TRACK = 'maincity1';

export function villageMusic(villageId: string | undefined): string {
    const track = (villageId && VILLAGE_TRACKS[villageId]) || DEFAULT_TRACK;

    return `${MUSIC_URL}/${track}.mp3`;
}

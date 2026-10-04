import { Head, Link, router } from '@inertiajs/react';
import { FastForward, SkipForward } from 'lucide-react';
import { useRef, useState } from 'react';
import BattleHud from '@/components/battle-hud';
import BattleScene from '@/components/battle-scene';
import GameWindow from '@/components/game-window';
import { applyStatusChange } from '@/game/battle/statuses';
import type { StatusChange } from '@/game/battle/statuses';
import type {
    ActiveStatus,
    BattleRecord,
    SkillInfo,
} from '@/game/battle/types';
import { schoolSound } from '@/game/sfx';
import { bag } from '@/routes';
import { fight as fightWave } from '@/routes/dungeon-runs';
import { show as showDungeon } from '@/routes/dungeons';
import { fight as hunt, show as showField } from '@/routes/fields';
import { show as tower, fight } from '@/routes/tower';

const TOP_FLOOR = 170;
const FAST_SPEED = 2;
const SKIP_SPEED = 25;

type Props = { battle: BattleRecord; skills: Record<string, SkillInfo> };

export default function Battle({ battle, skills }: Props) {
    // "Next floor" lands on this same page component; a new key resets the replay state.
    return <BattleView key={battle.id} battle={battle} skills={skills} />;
}

// How long a used jutsu's icon stays lit in the HUD.
const SKILL_GLOW_MS = 900;

function BattleView({ battle, skills }: Props) {
    const speed = useRef(1);
    const [fast, setFast] = useState(false);
    const [finished, setFinished] = useState(false);
    const [player, opponent] = battle.log.fighters;
    const [hp, setHp] = useState<[number, number]>([player.hp, opponent.hp]);
    const [mp, setMp] = useState<[number, number]>([
        player.mp ?? player.maxMp ?? 0,
        opponent.mp ?? opponent.maxMp ?? 0,
    ]);
    const [glowing, setGlowing] = useState<string | null>(null);
    // Jutsu without original art play the effect of the jutsu they borrow.
    const borrowedArt = Object.fromEntries(
        Object.entries(skills).flatMap(([id, skill]) =>
            skill.art ? [[id, skill.art]] : [],
        ),
    );
    const [statuses, setStatuses] = useState<[ActiveStatus[], ActiveStatus[]]>([
        [],
        [],
    ]);

    const updateStatus = (change: StatusChange) => {
        setStatuses((current) => {
            const next = applyStatusChange(current[change.actor], change);

            return change.actor === 0 ? [next, current[1]] : [current[0], next];
        });
    };

    const updateMp = (side: 0 | 1, value: number) => {
        setMp((current) =>
            side === 0 ? [value, current[1]] : [current[0], value],
        );
    };

    const lightSkill = (side: 0 | 1, skillId: string) => {
        if (side === 0) {
            setGlowing(skillId);
            setTimeout(
                () =>
                    setGlowing((current) =>
                        current === skillId ? null : current,
                    ),
                SKILL_GLOW_MS,
            );
        }
    };

    const updateHp = (side: 0 | 1, value: number) => {
        setHp((current) =>
            side === 0 ? [value, current[1]] : [current[0], value],
        );
    };

    const place =
        battle.dungeon?.name ??
        battle.field?.name ??
        `Training Tower · Floor ${battle.floor}`;

    const toggleFast = () => {
        speed.current = fast ? 1 : FAST_SPEED;
        setFast(!fast);
    };

    return (
        <>
            <Head title={`${place} — ${opponent.name}`} />
            <BattleScene
                log={battle.log}
                speed={speed}
                onFinished={() => setFinished(true)}
                onHp={updateHp}
                onMp={updateMp}
                onSkill={lightSkill}
                onStatus={updateStatus}
                skillName={(id) => skills[id]?.name ?? 'Jutsu'}
                skillSound={(id) => schoolSound(skills[id]?.school)}
                borrowedArt={borrowedArt}
            />
            <BattleHud
                fighters={battle.log.fighters}
                hp={hp}
                mp={mp}
                skills={skills}
                glowing={glowing}
                statuses={statuses}
                place={place}
            />

            {!finished && (
                <div className="absolute bottom-3 left-1/2 z-10 flex -translate-x-1/2 gap-3">
                    <button
                        type="button"
                        onClick={toggleFast}
                        aria-pressed={fast}
                        className="game-pill flex items-center justify-center gap-1"
                    >
                        <FastForward className="size-4" /> {fast ? '2x' : '1x'}
                    </button>
                    <button
                        type="button"
                        onClick={() => (speed.current = SKIP_SPEED)}
                        className="game-pill flex items-center justify-center gap-1"
                    >
                        <SkipForward className="size-4" /> Skip
                    </button>
                </div>
            )}

            {finished && (
                <BattleResult
                    battle={battle}
                    opponent={opponent.name}
                    place={place}
                />
            )}
        </>
    );
}

type ResultProps = { battle: BattleRecord; opponent: string; place: string };

/** Victory/defeat window with the rewards and where to go next. */
function BattleResult({ battle, opponent, place }: ResultProps) {
    const { dungeon, field, floor, rewards, won } = battle;

    if (dungeon) {
        return <DungeonResult battle={battle} opponent={opponent} />;
    }

    const home = field ? showField(field.scene) : tower();
    // Hunting-ground battles hunt the same monster again; tower battles fight their floor.
    const again = field
        ? hunt(field.monster).url
        : floor !== null
          ? fight(floor).url
          : null;

    return (
        <div className="absolute inset-0 z-20 flex items-center justify-center bg-black/50 px-4">
            <GameWindow
                title={won ? 'Victory!' : 'Defeat'}
                closeHref={home.url}
            >
                <div className="flex min-w-72 flex-col items-center gap-3 text-center">
                    <p className="text-slate-300">
                        {place}: {opponent}
                    </p>
                    {won ? (
                        <ul className="space-y-1 text-lg">
                            <li className="text-emerald-300">
                                +{rewards.exp} EXP
                            </li>
                            {rewards.gold > 0 && (
                                <li className="text-amber-300">
                                    +{rewards.gold} gold
                                </li>
                            )}
                            {rewards.drop && (
                                <li className="flex items-center justify-center gap-2 text-sky-300">
                                    <img
                                        src={rewards.drop.icon}
                                        alt=""
                                        className="size-8"
                                    />
                                    Found {rewards.drop.name} (Lv{' '}
                                    {rewards.drop.level})
                                </li>
                            )}
                            {rewards.levelUp && (
                                <li className="font-bold text-yellow-300">
                                    Level up!
                                </li>
                            )}
                            {!field && !rewards.firstClear && (
                                <li className="text-sm text-slate-400">
                                    Replays pay reduced EXP and no gold.
                                </li>
                            )}
                        </ul>
                    ) : (
                        <p className="text-slate-300">
                            {field
                                ? 'You were knocked out. Rest or drink a potion before hunting again.'
                                : 'You were knocked out. Train up and try again.'}
                        </p>
                    )}
                    <div className="mt-2 flex flex-wrap justify-center gap-2">
                        {!field &&
                            floor !== null &&
                            won &&
                            floor < TOP_FLOOR && (
                                <button
                                    type="button"
                                    onClick={() =>
                                        router.post(fight(floor + 1).url)
                                    }
                                    className="game-button px-4 py-1"
                                >
                                    Next floor
                                </button>
                            )}
                        {again && (
                            <button
                                type="button"
                                onClick={() => router.post(again)}
                                className="game-button px-4 py-1"
                            >
                                {won ? 'Fight again' : 'Retry'}
                            </button>
                        )}
                        <Link href={bag()} className="game-button px-4 py-1">
                            Bag
                        </Link>
                        <Link href={home} className="game-button px-4 py-1">
                            {field ? field.name : 'Tower'}
                        </Link>
                    </div>
                </div>
            </GameWindow>
        </div>
    );
}

/** A dungeon wave's result: stage and clear rewards, then on to the next wave. */
function DungeonResult({
    battle,
    opponent,
}: {
    battle: BattleRecord;
    opponent: string;
}) {
    const { rewards, won } = battle;
    const dungeon = battle.dungeon!;
    const home = showDungeon(dungeon.id);
    const title = rewards.dungeonCleared
        ? 'Dungeon cleared!'
        : won
          ? 'Victory!'
          : 'Defeat';

    return (
        <div className="absolute inset-0 z-20 flex items-center justify-center bg-black/50 px-4">
            <GameWindow title={title} closeHref={home.url}>
                <div className="flex min-w-72 flex-col items-center gap-3 text-center">
                    <p className="text-slate-300">
                        {dungeon.name}: {opponent}
                    </p>
                    {won ? (
                        <ul className="space-y-1 text-lg">
                            {rewards.stageCleared && (
                                <li className="text-amber-200">
                                    {rewards.stageCleared} cleared!
                                </li>
                            )}
                            <li className="text-emerald-300">
                                +{rewards.exp} EXP
                            </li>
                            {rewards.gold > 0 && (
                                <li className="text-amber-300">
                                    +{rewards.gold} gold
                                </li>
                            )}
                            {(rewards.coupons ?? 0) > 0 && (
                                <li className="text-rose-300">
                                    +{rewards.coupons} gift coupons
                                </li>
                            )}
                            {(rewards.honor ?? 0) > 0 && (
                                <li className="text-violet-300">
                                    +{rewards.honor} honor and medals
                                </li>
                            )}
                            {rewards.drop && (
                                <li className="flex items-center justify-center gap-2 text-sky-300">
                                    <img
                                        src={rewards.drop.icon}
                                        alt=""
                                        className="size-8"
                                    />
                                    Found {rewards.drop.name} (Lv{' '}
                                    {rewards.drop.level})
                                </li>
                            )}
                            {rewards.levelUp && (
                                <li className="font-bold text-yellow-300">
                                    Level up!
                                </li>
                            )}
                        </ul>
                    ) : (
                        <p className="text-slate-300">
                            You were knocked out and the run is over. Train up
                            and enter again.
                        </p>
                    )}
                    <div className="mt-2 flex flex-wrap justify-center gap-2">
                        {dungeon.active && (
                            <button
                                type="button"
                                onClick={() =>
                                    router.post(fightWave(dungeon.run).url)
                                }
                                className="game-button px-4 py-1"
                            >
                                Next wave
                            </button>
                        )}
                        <Link href={bag()} className="game-button px-4 py-1">
                            Inventory
                        </Link>
                        <Link href={home} className="game-button px-4 py-1">
                            {dungeon.name}
                        </Link>
                    </div>
                </div>
            </GameWindow>
        </div>
    );
}

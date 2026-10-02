import { Head, Link, router } from '@inertiajs/react';
import { FastForward, SkipForward } from 'lucide-react';
import { useRef, useState } from 'react';
import BattleHud from '@/components/battle-hud';
import BattleScene from '@/components/battle-scene';
import GameWindow from '@/components/game-window';
import type { BattleRecord, SkillInfo } from '@/game/battle/types';
import { bag } from '@/routes';
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

    const toggleFast = () => {
        speed.current = fast ? 1 : FAST_SPEED;
        setFast(!fast);
    };

    return (
        <>
            <Head title={`Floor ${battle.floor} — ${opponent.name}`} />
            <BattleScene
                log={battle.log}
                speed={speed}
                onFinished={() => setFinished(true)}
                onHp={updateHp}
                onMp={updateMp}
                onSkill={lightSkill}
                skillName={(id) => skills[id]?.name ?? 'Jutsu'}
            />
            <BattleHud
                fighters={battle.log.fighters}
                hp={hp}
                mp={mp}
                skills={skills}
                glowing={glowing}
                floor={battle.floor}
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
                <div className="absolute inset-0 z-20 flex items-center justify-center bg-black/50 px-4">
                    <GameWindow
                        title={battle.won ? 'Victory!' : 'Defeat'}
                        closeHref={tower().url}
                    >
                        <div className="flex min-w-72 flex-col items-center gap-3 text-center">
                            <p className="text-slate-300">
                                Floor {battle.floor}: {opponent.name}
                            </p>
                            {battle.won ? (
                                <ul className="space-y-1 text-lg">
                                    <li className="text-emerald-300">
                                        +{battle.rewards.exp} EXP
                                    </li>
                                    {battle.rewards.gold > 0 && (
                                        <li className="text-amber-300">
                                            +{battle.rewards.gold} gold
                                        </li>
                                    )}
                                    {battle.rewards.levelUp && (
                                        <li className="font-bold text-yellow-300">
                                            Level up!
                                        </li>
                                    )}
                                    {!battle.rewards.firstClear && (
                                        <li className="text-sm text-slate-400">
                                            Replays pay reduced EXP and no gold.
                                        </li>
                                    )}
                                </ul>
                            ) : (
                                <p className="text-slate-300">
                                    You were knocked out. Train up and try
                                    again.
                                </p>
                            )}
                            <div className="mt-2 flex flex-wrap justify-center gap-2">
                                {battle.won && battle.floor < TOP_FLOOR && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            router.post(
                                                fight(String(battle.floor + 1))
                                                    .url,
                                            )
                                        }
                                        className="game-button px-4 py-1"
                                    >
                                        Next floor
                                    </button>
                                )}
                                <button
                                    type="button"
                                    onClick={() =>
                                        router.post(
                                            fight(String(battle.floor)).url,
                                        )
                                    }
                                    className="game-button px-4 py-1"
                                >
                                    {battle.won ? 'Fight again' : 'Retry'}
                                </button>
                                <Link
                                    href={bag()}
                                    className="game-button px-4 py-1"
                                >
                                    Bag
                                </Link>
                                <Link
                                    href={tower()}
                                    className="game-button px-4 py-1"
                                >
                                    Tower
                                </Link>
                            </div>
                        </div>
                    </GameWindow>
                </div>
            )}
        </>
    );
}

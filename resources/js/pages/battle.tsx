import { Head, Link, router } from '@inertiajs/react';
import { FastForward, SkipForward } from 'lucide-react';
import { useRef, useState } from 'react';
import BattleScene from '@/components/battle-scene';
import GameWindow from '@/components/game-window';
import type { BattleRecord } from '@/game/battle/types';
import { bag } from '@/routes';
import { show as tower, fight } from '@/routes/tower';

const TOP_FLOOR = 170;
const FAST_SPEED = 2;
const SKIP_SPEED = 25;

export default function Battle({ battle }: { battle: BattleRecord }) {
    // "Next floor" lands on this same page component; a new key resets the replay state.
    return <BattleView key={battle.id} battle={battle} />;
}

function BattleView({ battle }: { battle: BattleRecord }) {
    const speed = useRef(1);
    const [fast, setFast] = useState(false);
    const [finished, setFinished] = useState(false);
    const opponent = battle.log.fighters[1];

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
            />

            {!finished && (
                <div className="absolute bottom-3 left-3 z-10 flex gap-2">
                    <button
                        type="button"
                        onClick={toggleFast}
                        aria-pressed={fast}
                        className="game-button flex items-center gap-1 px-3 py-1"
                    >
                        <FastForward className="size-4" /> {fast ? '2x' : '1x'}
                    </button>
                    <button
                        type="button"
                        onClick={() => (speed.current = SKIP_SPEED)}
                        className="game-button flex items-center gap-1 px-3 py-1"
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

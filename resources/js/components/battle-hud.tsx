import type { FighterInfo } from '@/game/battle/types';
import { characterAssets } from '@/types/game';

const FIGHT_UI = '/game-assets/ui/fight';
const SKILL_SLOTS = 15;

// Pixel geometry of the original bar frame (bar-frame-left.png, 425x29).
const HP_TRACK = { left: 38, top: 3, width: 370, height: 12 };
const MP_TRACK = { left: 26, top: 18, width: 306, height: 9 };

type Side = 'ally' | 'enemy';

type Props = {
    fighters: [FighterInfo, FighterInfo];
    hp: [number, number];
    floor: number;
};

function share(value: number, max: number): number {
    return Math.max(0, Math.min(1, value / Math.max(1, max)));
}

function faceOf(fighter: FighterInfo): string {
    return fighter.avatar
        ? characterAssets(fighter.avatar).face
        : (fighter.art?.face ?? '');
}

function mpOf(fighter: FighterInfo): [number, number] {
    const max = fighter.maxMp ?? 0;
    return [fighter.mp ?? max, max];
}

/** The original battle HUD: health/chakra bars with VS on top, stat panels on both sides. */
export default function BattleHud({ fighters, hp, floor }: Props) {
    const [player, opponent] = fighters;

    return (
        <div className="pointer-events-none absolute inset-0 z-10 select-none">
            <div className="absolute inset-x-0 top-0 flex items-start justify-between px-1 pt-1">
                <TopGroup side="ally" fighter={player} hp={hp[0]} />
                <div className="relative mt-0.5 h-[62px] w-[63px] shrink-0">
                    <img
                        src={`${FIGHT_UI}/vs-diamond.png`}
                        alt=""
                        className="absolute inset-0"
                    />
                    <img
                        src={`${FIGHT_UI}/vs-text.png`}
                        alt="Versus"
                        className="absolute top-[20px] left-px"
                    />
                </div>
                <TopGroup side="enemy" fighter={opponent} hp={hp[1]} />
            </div>

            <SidePanel
                side="ally"
                fighter={player}
                hp={hp[0]}
                subtitle={`Training Tower · Floor ${floor}`}
            />
            <SidePanel
                side="enemy"
                fighter={opponent}
                hp={hp[1]}
                subtitle={
                    opponent.isBoss
                        ? `Boss of floor ${floor}`
                        : `Floor ${floor}`
                }
            />
        </div>
    );
}

function TopGroup({
    side,
    fighter,
    hp,
}: {
    side: Side;
    fighter: FighterInfo;
    hp: number;
}) {
    const [mp, maxMp] = mpOf(fighter);
    const mirrored = side === 'enemy';

    return (
        <div
            className={`flex items-start gap-1 ${mirrored ? 'flex-row-reverse' : ''}`}
        >
            <div className="flex flex-col items-center">
                <span className="text-lg leading-none font-bold text-white [text-shadow:0_0_3px_#000,0_0_3px_#000]">
                    {mirrored ? 'Enemy' : 'Ally'}
                </span>
                <div className="relative h-[64px] w-[78px]">
                    <img
                        src={`${FIGHT_UI}/portrait-frame.png`}
                        alt=""
                        className="absolute inset-0"
                    />
                    <img
                        src={faceOf(fighter)}
                        alt={fighter.name}
                        className="absolute inset-[4px] h-[56px] w-[70px] object-cover object-top [clip-path:polygon(14%_0,100%_0,86%_100%,0_100%)]"
                        style={
                            mirrored && !fighter.avatar
                                ? { transform: 'scaleX(-1)' }
                                : undefined
                        }
                    />
                </div>
            </div>
            <div
                className="relative mt-[22px] h-[29px] w-[425px]"
                style={mirrored ? { transform: 'scaleX(-1)' } : undefined}
            >
                <img
                    src={`${FIGHT_UI}/bar-frame-left.png`}
                    alt=""
                    className="absolute inset-0"
                />
                <Fill
                    track={HP_TRACK}
                    image="hp-fill.png"
                    amount={share(hp, fighter.maxHp)}
                    label={`${fighter.name} health`}
                />
                <Fill
                    track={MP_TRACK}
                    image="mp-fill.png"
                    amount={share(mp, maxMp)}
                    label={`${fighter.name} chakra`}
                />
            </div>
        </div>
    );
}

function Fill({
    track,
    image,
    amount,
    label,
}: {
    track: typeof HP_TRACK;
    image: string;
    amount: number;
    label: string;
}) {
    return (
        <div
            role="meter"
            aria-label={label}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={Math.round(amount * 100)}
            className="absolute overflow-hidden transition-[width] duration-300"
            style={{
                left: track.left,
                top: track.top,
                height: track.height,
                width: track.width * amount,
            }}
        >
            <img
                src={`${FIGHT_UI}/${image}`}
                alt=""
                className="max-w-none"
                style={{ width: track.width, height: track.height }}
            />
        </div>
    );
}

function SidePanel({
    side,
    fighter,
    hp,
    subtitle,
}: {
    side: Side;
    fighter: FighterInfo;
    hp: number;
    subtitle: string;
}) {
    const [mp, maxMp] = mpOf(fighter);
    const stats: [string, string][] = [
        ['Defense', `${fighter.defense}`],
        ['Block', `${fighter.parry}%`],
        ['Dodge', `${fighter.dodge}%`],
        ['Critical', `${fighter.crit}%`],
        ['Counter', `${fighter.counter}%`],
        ['Initiative', `${fighter.priority}%`],
    ];

    return (
        <aside
            aria-label={`${side === 'ally' ? 'Ally' : 'Enemy'} details`}
            className={`absolute top-[110px] w-[180px] border-y border-white/10 bg-gradient-to-b from-black/80 to-zinc-900/85 py-2 text-[12px] text-zinc-200 shadow-xl ${
                side === 'ally'
                    ? 'left-0 rounded-r-md border-r pr-2 pl-2'
                    : 'right-0 rounded-l-md border-l pr-2 pl-2'
            }`}
        >
            <p className="flex justify-between font-semibold text-white">
                <span className="truncate">{fighter.name}</span>
                <span className="text-lime-400">LV:{fighter.level ?? '?'}</span>
            </p>
            <p className="truncate text-amber-300">{subtitle}</p>
            <Divider />
            <p className="flex justify-between">
                <span className="text-orange-400">Health</span>
                <span>
                    {hp} / {fighter.maxHp}
                </span>
            </p>
            <p className="flex justify-between">
                <span className="text-cyan-300">Chakra</span>
                <span>
                    {mp} / {maxMp}
                </span>
            </p>
            <Divider />
            <p className="flex justify-between">
                <span>Attack</span>
                <span>
                    {fighter.minAttack} - {fighter.maxAttack}
                </span>
            </p>
            <dl className="mt-0.5 grid grid-cols-2 gap-x-3">
                {stats.map(([label, value]) => (
                    <div key={label} className="flex justify-between">
                        <dt className="text-zinc-400">{label}</dt>
                        <dd>{value}</dd>
                    </div>
                ))}
            </dl>
            <Divider />
            <div className="flex items-center gap-2">
                <img
                    src={`${FIGHT_UI}/pet-empty.png`}
                    alt=""
                    className="size-[46px]"
                />
                <div>
                    <p className="text-zinc-400">No pet</p>
                    <div className="mt-1 flex gap-1">
                        <img
                            src={`${FIGHT_UI}/lock-slot.png`}
                            alt=""
                            className="size-[22px]"
                        />
                        <img
                            src={`${FIGHT_UI}/lock-slot.png`}
                            alt=""
                            className="size-[22px]"
                        />
                    </div>
                </div>
            </div>
            <Divider />
            <div
                aria-label="Skills (none learned yet)"
                className="grid grid-cols-5 gap-1"
            >
                {Array.from({ length: SKILL_SLOTS }, (_, index) => (
                    <img
                        key={index}
                        src={`${FIGHT_UI}/lock-slot.png`}
                        alt=""
                        className="size-[26px]"
                    />
                ))}
            </div>
        </aside>
    );
}

function Divider() {
    return (
        <hr className="my-1.5 border-0 bg-gradient-to-r from-transparent via-white/30 to-transparent pt-px" />
    );
}

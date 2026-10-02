import { AnimatedSprite, Assets, Graphics } from 'pixi.js';
import type { Application, Spritesheet } from 'pixi.js';
import { useCallback, useRef } from 'react';
import { usePixiApp } from '@/hooks/use-pixi-app';
import { characterAssets } from '@/types/game';

// Pixi's ticker targets 60 updates per second; SWF timelines run at their own fps.
const TICKER_FPS = 60;
const DEFAULT_FPS = 12;
// Chibi frames are ~100px tall with the SWF origin ~32px above the feet.
const SOURCE_HEIGHT = 100;
const FEET_BELOW_ORIGIN = 32;
// Share of the canvas height the chibi fills, and where its feet rest.
const HEIGHT_SHARE = 0.8;
const FEET_LINE = 0.94;

type Props = { avatar: string; className?: string };

/** The avatar's original idle animation, standing on a soft shadow. */
export default function CharacterSprite({ avatar, className }: Props) {
    const hostRef = useRef<HTMLDivElement>(null);

    const setup = useCallback(
        async (app: Application, isDisposed: () => boolean) => {
            const sheet = await Assets.load<Spritesheet>(
                characterAssets(avatar).idle,
            );

            if (isDisposed()) {
                return;
            }

            const shadow = new Graphics()
                .ellipse(0, 0, 30, 9)
                .fill({ color: 0x000000, alpha: 0.3 });
            const ninja = new AnimatedSprite(sheet.animations.idle);
            // tools/extract_character_assets.py stores the SWF frame rate in meta.fps.
            const meta = sheet.data.meta as { fps?: number };
            const fps = meta.fps ?? DEFAULT_FPS;
            ninja.animationSpeed = fps / TICKER_FPS;
            ninja.play();
            app.stage.addChild(shadow, ninja);

            const layout = () => {
                const scale =
                    (app.screen.height * HEIGHT_SHARE) / SOURCE_HEIGHT;
                const x = app.screen.width / 2;
                const feet = app.screen.height * FEET_LINE;
                ninja.scale.set(scale);
                ninja.position.set(x, feet - FEET_BELOW_ORIGIN * scale);
                shadow.scale.set(scale);
                shadow.position.set(x, feet);
            };

            layout();
            app.renderer.on('resize', layout);
        },
        [avatar],
    );

    const error = usePixiApp(hostRef, setup, { backgroundAlpha: 0 });

    return (
        <div ref={hostRef} className={className}>
            {error !== null && (
                <p className="p-2 text-xs text-muted-foreground">
                    Sprite not extracted yet.
                </p>
            )}
        </div>
    );
}

// Hand-drawn vector parts for the "Kaze" ninja: an original design made for
// Pocketo Ninjatos (not traced from the game). Each part is drawn around its
// joint (the pivot), facing left like the original motions. Units ~ pixels;
// the whole ninja is about 100 tall with its feet at y = 0.

export type Palette = {
    outline: string;
    skin: string;
    hair: string;
    outfit: string;
    outfitShade: string;
    accent: string;
    metal: string;
    shoe: string;
};

export const KAZE: Palette = {
    outline: '#1b1b2a',
    skin: '#ffd8b0',
    hair: '#2f2a3b',
    outfit: '#2c3e8f',
    outfitShade: '#22306e',
    accent: '#e0413a',
    metal: '#cfd6e2',
    shoe: '#3a3a4a',
};

const svg = (width: number, body: string) =>
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="-${width} -${width} ${width * 2} ${width * 2}">${body}</svg>`;

const stroke = (p: Palette) =>
    `stroke="${p.outline}" stroke-width="2" stroke-linejoin="round"`;

/** Pivot at the hip; leg hangs down, toe points left. */
export const leg = (p: Palette, shade = false) =>
    svg(
        40,
        `<rect x="-5.5" y="-2" width="11" height="25" rx="5" fill="${shade ? p.outfitShade : p.outfit}" ${stroke(p)}/>
         <rect x="-5" y="15" width="10" height="5" fill="#f4efe6" ${stroke(p)}/>
         <path d="M -10 30 Q -10 22 -2 22 L 5 22 Q 7 22 7 26 L 7 30 Z" fill="${p.shoe}" ${stroke(p)}/>`,
    );

/** Pivot at the hip centre; torso rises upward. */
export const torso = (p: Palette) =>
    svg(
        40,
        `<path d="M -12 2 L -13 -20 Q -12 -29 0 -29 Q 12 -29 13 -20 L 12 2 Z" fill="${p.outfit}" ${stroke(p)}/>
         <path d="M -6 -28 L 0 -17 L 6 -28" fill="none" stroke="${p.outfitShade}" stroke-width="2.5"/>
         <rect x="-13" y="-7" width="26" height="6" rx="2" fill="${p.accent}" ${stroke(p)}/>
         <circle cx="-4" cy="-4" r="2" fill="${p.metal}"/>`,
    );

/** Pivot at the shoulder; arm hangs down with a wrapped forearm and fist. */
export const arm = (p: Palette, shade = false) =>
    svg(
        40,
        `<rect x="-4.5" y="-3" width="9" height="15" rx="4" fill="${shade ? p.outfitShade : p.outfit}" ${stroke(p)}/>
         <rect x="-3.8" y="10" width="7.6" height="8" rx="2" fill="#f4efe6" ${stroke(p)}/>
         <circle cx="0" cy="21" r="5" fill="${p.skin}" ${stroke(p)}/>`,
    );

/** Scarf tail streaming behind (to the right); pivot at the neck. */
export const scarfTail = (p: Palette) =>
    svg(
        60,
        `<path d="M 4 -2 C 20 -6 30 2 44 -2 C 38 6 26 4 18 8 C 12 10 6 6 4 4 Z" fill="${p.accent}" ${stroke(p)}/>`,
    );

/** Scarf wrap around the neck; pivot at the neck. */
export const scarf = (p: Palette) =>
    svg(
        40,
        `<rect x="-12" y="-5" width="24" height="9" rx="4.5" fill="${p.accent}" ${stroke(p)}/>`,
    );

/** Pivot at the neck; big chibi head facing left with headband and spiky hair. */
export const head = (p: Palette) =>
    svg(
        60,
        `<path d="M 6 -40 L 24 -46 L 18 -34 L 30 -30 L 20 -24 L 27 -14 L 14 -12 Z" fill="${p.hair}" ${stroke(p)}/>
         <circle cx="0" cy="-21" r="21" fill="${p.skin}" ${stroke(p)}/>
         <path d="M -21 -24 Q -22 -44 0 -45 Q 20 -45 22 -24 L 14 -26 L 8 -20 L 2 -27 L -6 -21 L -10 -28 L -18 -22 Z" fill="${p.hair}" ${stroke(p)}/>
         <rect x="-22" y="-33" width="44" height="7" rx="2" fill="#24253a" ${stroke(p)}/>
         <rect x="-17" y="-34" width="14" height="9" rx="2" fill="${p.metal}" ${stroke(p)}/>
         <path d="M -14 -30 L -6 -30 M -10 -32.5 L -10 -27.5" stroke="${p.outline}" stroke-width="1.4"/>
         <path d="M 21 -31 C 30 -34 34 -28 42 -31 C 36 -25 30 -27 21 -26 Z" fill="#24253a" ${stroke(p)}/>
         <ellipse cx="-12" cy="-17" rx="2.8" ry="4.2" fill="${p.outline}"/>
         <ellipse cx="-2.5" cy="-17" rx="2.8" ry="4.2" fill="${p.outline}"/>
         <circle cx="-12.8" cy="-18.4" r="1" fill="#fff"/>
         <circle cx="-3.3" cy="-18.4" r="1" fill="#fff"/>
         <path d="M -10 -8 Q -7 -6 -4 -8" fill="none" stroke="${p.outline}" stroke-width="1.6" stroke-linecap="round"/>
         <ellipse cx="12" cy="-18" rx="3.5" ry="5" fill="${p.skin}" ${stroke(p)}/>
         <ellipse cx="-16" cy="-11" rx="3" ry="1.6" fill="#ff9e9e" opacity="0.6"/>`,
    );

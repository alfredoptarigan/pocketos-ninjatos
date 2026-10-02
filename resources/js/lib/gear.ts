// Shared by the character panel and the Equipment Shop.

export type GearStats = {
    slot: string;
    level: number;
    min_attack: number;
    max_attack: number;
    defense: number;
    max_hp: number;
    crit: number;
};

export const SLOT_LABELS: Record<string, string> = {
    weapon: 'Weapon',
    hat: 'Hat',
    armor: 'Armor',
    gloves: 'Gloves',
    belt: 'Belt',
    shoes: 'Shoes',
    amulet: 'Amulet',
    ring: 'Ring',
};

/** "Attack 14-16 · Defense 24" for a piece's non-zero stats. */
export function bonuses(piece: GearStats): string {
    return [
        piece.max_attack > 0 &&
            `Attack ${piece.min_attack}-${piece.max_attack}`,
        piece.defense > 0 && `Defense ${piece.defense}`,
        piece.max_hp > 0 && `Health +${piece.max_hp}`,
        piece.crit > 0 && `Critical +${piece.crit}%`,
    ]
        .filter(Boolean)
        .join(' · ');
}

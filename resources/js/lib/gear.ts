// Shared by the character panel and the Equipment Shop.

export type GearStats = {
    slot: string;
    level: number;
    min_attack: number;
    max_attack: number;
    defense: number;
    max_hp: number;
    crit: number;
    // Weapons only: the class of ninja that can hold it.
    weapon_class?: string | null;
};

// Original names (lg_Common_RolePopsinger*); config('game.equipment.weapon_classes').
export const WEAPON_CLASS_LABELS: Record<string, string> = {
    blunt: 'Blunt',
    sharp: 'Sharp',
    gloves: 'Fists',
};

/** A ninja only holds weapons of their outfit's class; null on either side fits all. */
export function canWield(piece: GearStats, ninjaClass: string | null): boolean {
    return (
        !piece.weapon_class || !ninjaClass || piece.weapon_class === ninjaClass
    );
}

/** "Weapon (Sharp)" for weapons, "Hat" for the rest. */
export function slotLabel(piece: GearStats): string {
    const slot = SLOT_LABELS[piece.slot] ?? piece.slot;

    return piece.weapon_class
        ? `${slot} (${WEAPON_CLASS_LABELS[piece.weapon_class]})`
        : slot;
}

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

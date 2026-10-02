<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Creatable Avatars
    |--------------------------------------------------------------------------
    |
    | The avatars offered on the original Pockie Ninja create screen, keyed by
    | "<sex>_<id>" (0 = male, 1 = female), with their per-level stat growth.
    | Art for each key is produced by tools/extract_character_assets.py.
    |
    */

    'avatars' => [
        // Per-level growth derived from the original rolebase aptitudes:
        // hp = MaxHPAdd/50, attack = max(StrAtkAdd, AgiAtkAdd)/400 (aptitudes
        // capped at 3000), defense = StaminaUp/20, dodge % = 5 + DodgeAdd/1000.
        '0_3' => ['hp' => 28, 'attack' => 4.25, 'defense' => 3.25, 'dodge' => 6],
        '0_9' => ['hp' => 20, 'attack' => 5.5, 'defense' => 4.0, 'dodge' => 7],
        '0_12' => ['hp' => 24, 'attack' => 3.5, 'defense' => 4.0, 'dodge' => 6],
        '0_14' => ['hp' => 24, 'attack' => 5.25, 'defense' => 4.0, 'dodge' => 7],
        '0_16' => ['hp' => 16, 'attack' => 7.5, 'defense' => 5.75, 'dodge' => 8],
        '0_17' => ['hp' => 36, 'attack' => 3.0, 'defense' => 2.0, 'dodge' => 6],
        '0_18' => ['hp' => 22, 'attack' => 4.5, 'defense' => 4.5, 'dodge' => 6],
        '0_19' => ['hp' => 24, 'attack' => 3.5, 'defense' => 4.0, 'dodge' => 6],
        '0_20' => ['hp' => 22, 'attack' => 3.5, 'defense' => 4.5, 'dodge' => 6],
        '1_26' => ['hp' => 20, 'attack' => 4.0, 'defense' => 4.5, 'dodge' => 7],
        '1_32' => ['hp' => 18, 'attack' => 6.5, 'defense' => 5.0, 'dodge' => 8],
        '1_33' => ['hp' => 24, 'attack' => 3.25, 'defense' => 4.0, 'dodge' => 6],
        '1_34' => ['hp' => 22, 'attack' => 5.25, 'defense' => 4.5, 'dodge' => 7],
        '1_36' => ['hp' => 22, 'attack' => 5.0, 'defense' => 3.0, 'dodge' => 6],
        '1_39' => ['hp' => 16, 'attack' => 4.75, 'defense' => 6.0, 'dodge' => 7],
        '1_61' => ['hp' => 30, 'attack' => 3.5, 'defense' => 3.5, 'dodge' => 6],
        '1_63' => ['hp' => 26, 'attack' => 4.25, 'defense' => 3.5, 'dodge' => 6],
        '1_65' => ['hp' => 12, 'attack' => 7.25, 'defense' => 6.5, 'dodge' => 8],
    ],

    /*
    |--------------------------------------------------------------------------
    | Starting Gold
    |--------------------------------------------------------------------------
    |
    | Gold a freshly created ninja carries into the village.
    |
    */

    'starting_gold' => 1000,

    /*
    |--------------------------------------------------------------------------
    | Villages
    |--------------------------------------------------------------------------
    |
    | Main-city scenes from the original game, keyed by their scene id.
    | Art is produced by tools/extract_village_assets.py. Names are ours:
    | the backup only ships Chinese text.
    |
    */

    'home_village' => '111',

    'villages' => [
        '111' => 'Leaf Village',
        '121' => 'Mist Village',
        '131' => 'Cloud Village',
        '141' => 'Wind Village',
        '151' => 'Sound Village',
        '161' => 'Waterfall Village',
        '171' => 'Shadow Village',
    ],

    /*
    |--------------------------------------------------------------------------
    | Skills
    |--------------------------------------------------------------------------
    |
    | Skill-panel jutsu from the original clientskill table, keyed by their
    | original id (icons: public/game-assets/skills/<id>.png). Chance, power and
    | effects come from the original descriptions; 'chakra' is the original
    | MPCostMul, turned into a cost by combat.skill_chakra_divisor. Seconds in
    | the originals become turns (about two seconds each).
    |
    | kind: strike     replaces the normal attack (power % of base attack)
    |       follow_up  extra hit after a landed normal attack
    |       extra      extra hit before the ninja's own action
    |       counter    hits back after being attacked
    |       block      cancels an incoming attack
    |       reflect    returns a share of damage taken
    |       heal       heals a share of lost health after being hurt
    |       revive     comes back with a share of max health on knock-out
    |
    | 'requires' is the previous jutsu of the same school, as in the original.
    |
    */

    'skills' => [
        '1808' => ['name' => 'Fireball', 'school' => 'Fire', 'kind' => 'strike', 'chance' => 33, 'power' => 130, 'chakra' => 100, 'level' => 1, 'requires' => null,
            'description' => 'Gathers chakra in the mouth and breathes out a ball of fire. 130% damage.'],
        '1802' => ['name' => 'Falling Thunder', 'school' => 'Lightning', 'kind' => 'follow_up', 'chance' => 24, 'power' => 80, 'chakra' => 100, 'level' => 1, 'requires' => null,
            'description' => 'Adds a bolt of lightning to a normal attack. 80% extra damage.'],
        '1825' => ['name' => 'Chidori', 'school' => 'Lightning', 'kind' => 'strike', 'chance' => 17, 'power' => 160, 'chakra' => 200, 'level' => 8, 'requires' => '1802',
            'description' => 'Condensed chakra turned into lightning in the hand. 160% damage.'],
        '1826' => ['name' => 'Flash Step', 'school' => 'Wind', 'kind' => 'extra', 'chance' => 20, 'power' => 65, 'chakra' => 110, 'level' => 1, 'requires' => null,
            'description' => 'A burst of speed that lands an extra attack at 65% power before acting.'],
        '3811' => ['name' => 'Sand Storm', 'school' => 'Wind', 'kind' => 'counter', 'chance' => 22, 'power' => 144, 'chakra' => 110, 'level' => 5, 'requires' => '1826',
            'description' => 'Swirling sand strikes back when attacked. 144% damage.'],
        '1813' => ['name' => 'Gale Palm', 'school' => 'Wind', 'kind' => 'follow_up', 'chance' => 27, 'power' => 108, 'chakra' => 80, 'level' => 10, 'requires' => '3811',
            'description' => 'A palm strike wrapped in a cutting gale after a normal attack. 108% extra damage.'],
        '1810' => ['name' => 'Rasengan', 'school' => 'Wind', 'kind' => 'strike', 'chance' => 26, 'power' => 180, 'chakra' => 140, 'level' => 15, 'requires' => '1813', 'self_damage' => 30,
            'description' => 'A spinning sphere of chakra. 180% damage, but 30% of it recoils on the user.'],
        '1828' => ['name' => 'Bomb', 'school' => 'Ninja Tools', 'kind' => 'strike', 'chance' => 33, 'power' => 210, 'chakra' => 0, 'level' => 6, 'requires' => null, 'backfire' => true,
            'description' => 'Throws a powerful bomb. 210% damage, but the longer the fight, the likelier it is thrown back.'],
        '1807' => ['name' => 'Lotus', 'school' => 'Taijutsu', 'kind' => 'strike', 'chance' => 23, 'power' => 250, 'chakra' => 0, 'level' => 3, 'requires' => null, 'self_stun' => 4,
            'description' => 'A flurry of blows at 250% damage that leaves the user exhausted for 4 turns.'],
        '1822' => ['name' => 'Monstrous Strength', 'school' => 'Taijutsu', 'kind' => 'strike', 'chance' => 15, 'power' => 100, 'chakra' => 0, 'level' => 12, 'requires' => '1807', 'max_hp_damage' => 7, 'stun' => 3, 'max_uses' => 3,
            'description' => 'A crushing leap: 100% damage plus 7% of the target\'s max health, stunning it for 3 turns. Up to 3 times a fight.'],
        '1827' => ['name' => 'Assassination', 'school' => 'Genjutsu', 'kind' => 'strike', 'chance' => 28, 'power' => 100, 'chakra' => 70, 'level' => 2, 'requires' => null, 'lifesteal' => 50,
            'description' => 'Strikes a vital point and drains life: 100% damage, half of it healed.'],
        '3826' => ['name' => 'Substitution', 'school' => 'Genjutsu', 'kind' => 'block', 'chance' => 17, 'power' => 0, 'chakra' => 0, 'level' => 9, 'requires' => '1827',
            'description' => 'Swaps places with a decoy and takes no damage from an attack.'],
        '3806' => ['name' => 'Earth Wall', 'school' => 'Earth', 'kind' => 'reflect', 'chance' => 19, 'power' => 60, 'chakra' => 0, 'level' => 7, 'requires' => null,
            'description' => 'Raises a wall of earth that returns 60% of the damage taken.'],
        '1829' => ['name' => 'Mystical Palm', 'school' => 'Medical', 'kind' => 'heal', 'chance' => 22, 'power' => 11, 'chakra' => 120, 'level' => 4, 'requires' => null, 'max_uses' => 3,
            'description' => 'Heals 11% of lost health after being hurt. Up to 3 times a fight.'],
        '3803' => ['name' => 'Creation Rebirth', 'school' => 'Medical', 'kind' => 'revive', 'chance' => 100, 'power' => 25, 'chakra' => 400, 'level' => 20, 'requires' => '1829', 'max_uses' => 1,
            'description' => 'Comes back from a knock-out with 25% health. Less likely the longer the fight lasts.'],
    ],

    // Gold to learn a jutsu: skill_gold_base + required level * skill_gold_per_level.
    'skill_gold_base' => 50,
    'skill_gold_per_level' => 50,

    /*
    |--------------------------------------------------------------------------
    | Combat
    |--------------------------------------------------------------------------
    |
    | The server formulas are not in the backup; these are ours, calibrated so
    | a level 1 ninja matches the recorded fights (HP 110, MP 56, Atk 20-25,
    | Dodge 5, Crit 5) and can clear the first Training Tower floors.
    |
    */

    'combat' => [
        'base_hp' => 110,
        'base_mp' => 56,
        'mp_per_level' => 6,
        'base_min_attack' => 20,
        // Max attack is this % above min attack (level 1: 20-25).
        'max_attack_percent' => 125,
        'crit' => 5,
        'crit_multiplier' => 200,
        'parry' => 2,
        // Damage taken is multiplied by 1 - def / (def + defense_scale).
        'defense_scale' => 500,
        'max_turns' => 60,
        // A jutsu costs ceil(chakra * max chakra / skill_chakra_divisor).
        'skill_chakra_divisor' => 1000,
        // Bomb is thrown back with this % chance per turn played (capped).
        'bomb_backfire_per_turn' => 1,
        'bomb_backfire_cap' => 40,
        // Creation Rebirth's chance drops by this % per turn played (floor 10).
        'revive_decay_per_turn' => 2,
        'revive_min_chance' => 10,
        // Health and chakra recover by this % of the maximum per minute.
        'regen_percent_per_minute' => 5,
        'max_level' => 100,
        // Experience to go from level L to L+1: round(exp_base * L ^ exp_exponent),
        // fitted so tower exp keeps players near the floor opponents' levels.
        'exp_base' => 120,
        'exp_exponent' => 0.8,
    ],

    'tower' => [
        // First clear pays gold_base + floor * gold_per_floor; replays pay
        // replay_exp_percent of the floor's exp and no gold.
        'gold_base' => 20,
        'gold_per_floor' => 5,
        'replay_exp_percent' => 25,
    ],

];

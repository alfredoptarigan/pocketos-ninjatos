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

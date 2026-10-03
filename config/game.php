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

    // Skills (jutsu) live in config/skills.php.

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
        // A jutsu costs ceil(chakra * max chakra / skill_chakra_divisor): Fireball
        // (100) 5% of max chakra, Static Field (300) 15%, so a ninja can cast
        // about a dozen jutsu a fight before running dry.
        'skill_chakra_divisor' => 2000,
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

    // Fights, searches and draws a player may send per minute (anti-botting).
    'actions_per_minute' => 30,

    'tower' => [
        // First clear pays gold_base + floor * gold_per_floor; replays pay
        // replay_exp_percent of the floor's exp and no gold.
        'gold_base' => 20,
        'gold_per_floor' => 5,
        'replay_exp_percent' => 25,
        // Gift coupons for a first clear, spent at the Wishing Pot.
        'coupons_per_first_clear' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Outfits and the Wishing Pot
    |--------------------------------------------------------------------------
    |
    | Outfits (Naruto/Bleach characters) come from tools/extract_outfit_assets.py
    | with the original rarity: grey < blue < orange. Wearing one changes the
    | ninja's look and adds bonus_percent to max health, attack and defense;
    | level growth still follows the created avatar.
    |
    | Pots cost gift coupons and roll a rarity by weight, then a random outfit
    | of that rarity for the ninja's sex. The colour pots are the original
    | Exotic Wishing Pots; the Ninja Wishing Pot is our mixed-odds pot.
    |
    */

    'starting_coupons' => 10,

    // Daily sign-in (Gifts menu): exp_percent of the exp to the next level,
    // plus gift coupons by day of a 7-day streak. A missed day restarts it.
    'sign_in' => [
        'exp_percent' => 50,
        'coupons' => [1, 1, 2, 2, 3, 3, 5],
    ],

    'outfits' => [
        'bonus_percent' => ['grey' => 0, 'blue' => 5, 'orange' => 10],
        // Drawing an outfit the ninja already owns gives outfit shards instead.
        'duplicate_shards' => ['grey' => 2, 'blue' => 5, 'orange' => 15],
        // Upgrading to +N costs N times gold and shards, needs character level
        // level_step * (N - 1) (the original avataritem UseLevel) and adds
        // bonus_per_level percent per level on top of the rarity bonus.
        'upgrade' => ['max_level' => 27, 'gold' => 200, 'shards' => 1, 'level_step' => 3, 'bonus_per_level' => 1],
        // Event and mascot outfits never come out of a pot.
        'event_only' => ['1_86', '0_88', '1_89', '1_90', '0_98'],
        'pots' => [
            'ninja' => ['name' => 'Ninja Wishing Pot', 'price' => 10, 'odds' => ['grey' => 70, 'blue' => 25, 'orange' => 5]],
            'grey' => ['name' => 'Grey Outfit Wishing Pot', 'price' => 5, 'odds' => ['grey' => 1]],
            'blue' => ['name' => 'Blue Outfit Wishing Pot', 'price' => 25, 'odds' => ['blue' => 1]],
            'orange' => ['name' => 'Orange Outfit Wishing Pot', 'price' => 100, 'odds' => ['orange' => 1]],
            // Original Magic Wishing Pots: the ninja picks one outfit of the list
            // (only those with art in the backup; Akatsuki, Pain, Sage Naruto have none).
            'shippuden' => ['name' => 'Shippuden Wishing Pot', 'price' => 120, 'pick' => ['0_24', '1_31', '1_67', '1_70']],
            's_rank' => ['name' => 'S-rank Ninja Wishing Pot', 'price' => 150, 'pick' => ['0_50', '0_53', '1_30', '1_44']],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Avatar collection and attributes
    |--------------------------------------------------------------------------
    |
    | A +record_level outfit can be recorded in the collection for its
    | original Strength/Agility/Stamina (avatarcollect). Recording enough
    | outfits of one rarity reaches a tier (avatarcollectleveladd): from that
    | character level, health and attack rise by the tier's percent. The
    | original SpdRate has no stat here. Tiers without a level in the data
    | use 70, like orange tier 6.
    |
    | We have no Strength/Agility/Stamina: they convert to attack, dodge and
    | health with the rates under 'attributes'.
    |
    */

    'collection' => [
        'record_level' => 2,
        // rarity => list of [outfits recorded, character level, percent, title earned
        // on recording that many (title code, regardless of level)]
        'tiers' => [
            'orange' => [
                [5, 25, 3.2, 'AvatarTitle101'], [10, 35, 4.9, 'AvatarTitle102'], [15, 45, 6.1, 'AvatarTitle103'],
                [20, 55, 7.1, 'AvatarTitle104'], [25, 65, 8, 'AvatarTitle105'], [30, 70, 9, 'AvatarTitle106'],
                [35, 75, 10, 'AvatarTitle107'],
            ],
            'blue' => [
                [5, 25, 2.6, 'AvatarTitle201'], [10, 35, 4, 'AvatarTitle202'], [15, 45, 5, 'AvatarTitle203'],
                [20, 55, 5.9, 'AvatarTitle204'], [25, 65, 6.6, 'AvatarTitle205'], [30, 70, 7.1, null],
            ],
            'grey' => [
                [5, 25, 2.1, 'AvatarTitle301'], [10, 35, 3.2, 'AvatarTitle302'], [15, 45, 4, 'AvatarTitle303'],
                [20, 55, 4.7, 'AvatarTitle304'], [25, 65, 5.3, 'AvatarTitle305'], [30, 70, 5.7, null],
            ],
        ],
    ],

    'attributes' => [
        'strength_attack' => 1,
        'stamina_hp' => 5,
        // One percent of dodge per this many Agility.
        'agility_per_dodge' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Achievements
    |--------------------------------------------------------------------------
    |
    | The original accomplishment table has 68 achievements; most need systems
    | not built yet (arena, duels, missions, VIP, pets, cards). Only those
    | under 'tracked' are seeded (AchievementSeeder), with target, points and
    | reward title from the original data. Each counter is a characters column
    | ('level', 'gold_spent', ...) or 'points', the sum of completed points.
    |
    */

    'achievements' => [
        'counters' => [
            'level' => 'Reach level {target}.',
            'gold_spent' => 'Spend {target} gold.',
            'bosses_defeated' => 'Defeat {target} field bosses.',
            'sign_in_days' => 'Sign in on {target} days.',
            'points' => 'Collect {target} achievement points.',
        ],
        'tracked' => [
            1010101 => ['counter' => 'level', 'name' => 'Prominent Ninja'],
            1010102 => ['counter' => 'level', 'name' => 'Early Peep of Nindo'],
            1010103 => ['counter' => 'level', 'name' => 'Ninjutsu Beginner'],
            1010104 => ['counter' => 'level', 'name' => 'Ninjutsu Limits'],
            1010105 => ['counter' => 'level', 'name' => 'Ninjutsu Master'],
            1010106 => ['counter' => 'level', 'name' => 'Legendary Ninja'],
            1010107 => ['counter' => 'level', 'name' => 'Ninja Sorcerer'],
            1020210 => ['counter' => 'gold_spent', 'name' => 'Spend Like Water'],
            1020211 => ['counter' => 'gold_spent', 'name' => 'Big Spender'],
            1020212 => ['counter' => 'gold_spent', 'name' => 'Treat Money Like Dirt'],
            4080724 => ['counter' => 'bosses_defeated', 'name' => 'Demon Hunter'],
            5000749 => ['counter' => 'sign_in_days', 'name' => 'Perseverance'],
            5150753 => ['counter' => 'points', 'name' => 'Essence of Final Release'],
            5150754 => ['counter' => 'points', 'name' => 'Wake of Sharingan'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Honor and Honor Exchange
    |--------------------------------------------------------------------------
    |
    | The original honor came from Nation and Tailed Beast fights, which are
    | not built: here new tower floors and dungeon clears pay honor, and the
    | same amount of medals. Total honor sets the rank (one per per_rank,
    | up to the last rank). The Honor Exchange trades medals for EXP at the
    | ninja's rank (honorexchangeexp: Model = medals, Exp), daily_exchanges
    | times a day. Its "Equipment Set" tab waits for equipment sets.
    |
    */

    'honor' => [
        'tower_first_clear' => 5,
        'dungeon_clear' => 30,
        'per_rank' => 150,
        'daily_exchanges' => 5,
        // [medals, exp] for ranks 1..22
        'exchange' => [
            [90, 70], [90, 250], [100, 550], [110, 840], [120, 1200], [130, 1600], [140, 2100], [150, 2600],
            [160, 3300], [170, 3900], [180, 4800], [200, 5500], [210, 6500], [220, 7300], [230, 8500],
            [250, 9500], [260, 11000], [280, 12000], [290, 13500], [300, 14500], [320, 16500], [330, 18000],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Equipment
    |--------------------------------------------------------------------------
    |
    | The catalogue comes from tools/extract_equipment_assets.py. Tower wins
    | drop a piece of the best tier at or below the opponent's level: always
    | on a first clear, replay_drop_percent of the time on replays.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Hunting grounds (world map)
    |--------------------------------------------------------------------------
    |
    | Field monsters pay their original NpcExp times exp_multiplier and
    | level * gold_per_level gold, and sometimes drop gear. Health and chakra
    | carry over between field fights (unlike the tower).
    |
    */

    'fields' => [
        'exp_multiplier' => 2,
        'gold_per_level' => 2,
        'drop_percent' => 5,
        'boss_drop_percent' => 25,
    ],

    /*
    |--------------------------------------------------------------------------
    | Dungeons (original tollgates)
    |--------------------------------------------------------------------------
    |
    | From tools/extract_dungeon_assets.py. A run fights each stage's waves in
    | order, one battle per wave against its leader; wounds carry over and a
    | loss ends the run. Waves pay their leader's exp times exp_multiplier,
    | stage bosses always drop gear, each stage pays its original reward and a
    | full clear pays the dungeon's reward plus clear_coupons gift coupons.
    | Runs per day per dungeon come from the original TotalTimes.
    |
    */

    'dungeons' => [
        'exp_multiplier' => 2,
        'clear_coupons' => 2,
        // Stage bosses were tuned for two-player teams; solo they keep this
        // share of health and attack. A stage clear also restores the ninja.
        'solo_boss_percent' => 70,
    ],

    /*
    | Searching a spot in a hunting ground (original roleoutsearch): a money
    | pouch holds (area level + 9) * gold_per_level gold; a found potion costs
    | at most max(potion_price_floor, area level * 5) in the pharmacy.
    */

    'search' => [
        'gold_per_level' => 5,
        'potion_price_floor' => 20,
    ],

    'equipment' => [
        'slots' => ['weapon', 'hat', 'armor', 'gloves', 'belt', 'shoes', 'amulet', 'ring'],
        'replay_drop_percent' => 15,
        // The Equipment Shop sells the basic tiers up to stock_levels_ahead
        // above the ninja's level for price * (buy_multiplier + level) and
        // buys gear back for sell_percent of that.
        'stock_levels_ahead' => 10,
        'buy_multiplier' => 10,
        'sell_percent' => 25,
    ],

];

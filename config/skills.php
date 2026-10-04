<?php

/*
|--------------------------------------------------------------------------
| Skills (jutsu)
|--------------------------------------------------------------------------
|
| The 50 skill-panel skills of the original clientskill table (Type 1): ten
| schools, each with a passive and four active jutsu (tiers 1-4, ClipName
| ClipGrid_<School>_Ahead<tier>). Ids, prerequisites ('requires', PreID) and
| chakra (MPCostMul) are original; chances, damage and effects come from
| the original descriptions (lg_SkillDes_<id>) and the wiki. Seconds become
| turns (about two seconds each). Icons: public/game-assets/skills/<id>.png,
| status icons: public/game-assets/statuses/<status>.png.
|
| kind (when the jutsu rolls):
|   strike        replaces the normal attack (power % of base attack)
|   follow_up     after a landed normal attack (power 0: effect only)
|   extra         extra hit before the ninja's own action
|   prepare       before the ninja's own action, effect only
|   before_enemy  before the opponent acts, effect only
|   counter       hits back after being attacked
|   block         cancels an incoming attack
|   reflect       returns a share of the damage taken
|   heal          heals a share of lost health after being hurt
|   hurt          after being hurt, effect only
|   revive        comes back with a share of max health on knock-out
|   battle        always on from the start, if its chakra can be paid
|
| effect (besides damage; its parameters sit in the same entry):
|   burn          target loses 'amount' % of the hit over 'turns' turns
|   bloodboil     +amount chance to every jutsu for 'turns' turns; chakra
|                 jutsu hurt the user for 'recoil' % of their damage
|   sunset        element jutsu may deal double damage: 'amount' % per turn
|                 played, up to 'cap' %
|   mist          incoming attacks and counters may miss: 'amount' % per
|                 turn played, up to 'cap' %; nobody burns
|   waterfall     opponent stunned 'turns' turns, only while the user has a debuff
|   freeze        target cannot act until hit (at most 'turns' turns); that
|                 hit deals double damage; no fire jutsu while frozen
|   invulnerable  no damage for 'turns' turns
|   slow          target is 'amount' % slower for 'turns' turns
|   clay          after 'turns' turns the target takes again all the damage
|                 it took meanwhile
|   prison        drains 'amount' % of the target's max chakra and lowers
|                 its defense by 'defense' for the rest of the fight
|   shield        absorbs damage up to 'amount' % of max health; refills by
|                 a third of the user's lightning damage
|   cloud         a thunder cloud hits a random side for 'amount' % of its
|                 max health after each of the user's turns
|   chakra_burn   the target's jutsu cost twice the chakra for the rest of the fight
|   gates         when hurt, opens a gate every 'cooldown' turns (up to 8),
|                 'amount' % faster each
|   poison        target loses 'amount' % of max health before each move
|   drunk         opponent's hits miss 'amount' % more, user crits 'crit' %
|                 more; burns on a drunk target hurt half as much again
|   snare         target's dodge -'amount' for a turn
|   cleanse       removes one random debuff that does not stop moving
|   cursed_seal   +0.5 attack % per health % lost; seals on the user last half as long
|   seal          target cannot use element jutsu for 'turns' turns
|   dead_demon    target deals and heals 'amount' % less ('body' % with body
|                 jutsu and Assassinate) for 'turns' turns
|   charm         target cannot move for 'turns' turns; needs a buff on it,
|                 lightning jutsu break it
|   mirage        target loses 'amount' % of max health and chakra each
|                 turn until it uses a chakra jutsu
|   regen         user heals 'amount' % of max health before its next 'turns' moves
|   chakra_cut    target loses 'amount' % of its max chakra
|
| 'uses_group' shares max_uses between jutsu (Mystical Palm and Pre-Healing);
| 'excludes' is a jutsu that cannot run together with this one.
|
*/

return [
    // Skill points: one per character level after the first, so all 40
    // jutsu by level 41; later points go into upgrades.
    'max_level' => 13,
    // Each level above 1 adds this chance and power percent.
    'upgrade' => ['chance' => 1, 'power_percent' => 5],
    // Passives level up with the character (upskillcfg: levels 1, 11, ... 91);
    // each level adds this chance and power percent to its school's jutsu.
    'passive' => ['levels' => [1, 11, 21, 31, 41, 51, 61, 71, 81, 91], 'chance' => 1, 'power_percent' => 3],
    // Equipped slots per page: free ones by character level, the rest bought
    // with gift coupons, in order.
    'slots' => ['pages' => 3, 'total' => 10, 'free' => [1 => 3, 10 => 4, 20 => 5, 30 => 6], 'prices' => [20, 40, 60, 80]],
    'reset_coupons' => 8,
    // Speed: a slowed side loses its turn with this share of the slow %; a
    // hasted side acts again with this share of the speed %.
    'speed_chance_percent' => 50,

    // Opponents' jutsu. The client data never says which jutsu a monster
    // knows (that lived on the server), so each monster draws a fixed set by
    // its name: base + one per per_levels levels (+boss_bonus for bosses, at
    // most max), from tiers up to 1 + level / tier_per_levels, at skill level
    // 1 + level / skill_level_per_levels.
    // Ultimates (clientskill Type 2, "Secret Technique"): one per outfit, id
    // id_base + outfit id. The original only says it may finish an opponent on
    // low health, more likely the more the outfits differ; the recordings in
    // source/dazhao fire at 7-9% health. The numbers are ours: tried instead of
    // the attack when the target is at or below below_health_percent, chance %
    // plus per_outfit_level for each upgrade level above the opponent's outfit
    // (min_chance..max_chance); no chakra, no limit. From upgraded_from_level
    // the stronger cinematic plays (avatar 1901 is Kurosaki Ichigo +19).
    'ultimate' => [
        'name' => 'Secret Technique',
        'description' => 'May finish an opponent at low health in one blow, more often the more your outfit is upgraded than theirs. No chakra.',
        'id_base' => 1900, 'below_health_percent' => 10, 'chance' => 30, 'per_outfit_level' => 2,
        'min_chance' => 10, 'max_chance' => 60, 'upgraded_from_level' => 19,
    ],

    'monsters' => ['base' => 1, 'per_levels' => 20, 'boss_bonus' => 2, 'max' => 6, 'tier_per_levels' => 25, 'skill_level_per_levels' => 10],

    // Panel columns, left to right. Element schools are the five basic
    // elements (Sunset, Five Element Seal).
    'schools' => [
        'fire' => ['name' => 'Fire Release', 'passive' => '2801', 'element' => true],
        'water' => ['name' => 'Water Release', 'passive' => '2802', 'element' => true],
        'earth' => ['name' => 'Earth Release', 'passive' => '2803', 'element' => true],
        'lightning' => ['name' => 'Lightning Release', 'passive' => '2804', 'element' => true],
        'wind' => ['name' => 'Wind Release', 'passive' => '2805', 'element' => true],
        'body' => ['name' => 'Body Technique', 'passive' => '2806', 'element' => false],
        'tools' => ['name' => 'Ninja Tools', 'passive' => '2807', 'element' => false],
        'seal' => ['name' => 'Sealing Technique', 'passive' => '2808', 'element' => false],
        'illusion' => ['name' => 'Illusion Technique', 'passive' => '2809', 'element' => false],
        'healing' => ['name' => 'Healing Jutsu', 'passive' => '2810', 'element' => false],
    ],

    'skills' => [
        // Fire
        '1808' => ['name' => 'Fireball', 'school' => 'fire', 'tier' => 1, 'requires' => null, 'kind' => 'strike', 'chance' => 33, 'power' => 130, 'chakra' => 100,
            'effect' => 'burn', 'amount' => 40, 'turns' => 6,
            'description' => 'A ball of fire from the mouth: 130% damage, and the target burns for 40% of it over 12 seconds.'],
        '3809' => ['name' => 'Balsam', 'school' => 'fire', 'tier' => 2, 'requires' => '1808', 'kind' => 'counter', 'chance' => 38, 'power' => 40, 'chakra' => 30,
            'effect' => 'burn', 'amount' => 40, 'turns' => 6,
            'description' => 'When hit, sparks fly back: 40% damage, and the attacker burns for 40% of it over 12 seconds.'],
        '3804' => ['name' => 'Bloodboil', 'school' => 'fire', 'tier' => 3, 'requires' => '3809', 'kind' => 'prepare', 'chance' => 32, 'power' => 0, 'chakra' => 230, 'max_uses' => 1,
            'effect' => 'bloodboil', 'amount' => 10, 'recoil' => 20, 'turns' => 10,
            'description' => 'Burns inner chakra for 21 seconds: every jutsu is 10% likelier, but chakra jutsu hurt the user for 20% of their damage. Once a fight.'],
        '3815' => ['name' => 'Sunset', 'school' => 'fire', 'tier' => 4, 'requires' => '3804', 'kind' => 'battle', 'chance' => 100, 'power' => 0, 'chakra' => 340, 'excludes' => '3813',
            'effect' => 'sunset', 'amount' => 2, 'cap' => 50,
            'description' => 'Element jutsu may deal double damage, likelier the longer the fight lasts. Cannot run with Mist-hide.'],

        // Water
        '3813' => ['name' => 'Mist-hide', 'school' => 'water', 'tier' => 1, 'requires' => null, 'kind' => 'battle', 'chance' => 100, 'power' => 0, 'chakra' => 340, 'excludes' => '3815',
            'effect' => 'mist', 'amount' => 2, 'cap' => 40,
            'description' => 'A thick mist: attacks and counters may miss, likelier the longer the fight lasts, and nobody burns. Cannot run with Sunset.'],
        '3820' => ['name' => 'Giant Waterfall', 'school' => 'water', 'tier' => 2, 'requires' => '3813', 'kind' => 'before_enemy', 'chance' => 43, 'power' => 0, 'chakra' => 130, 'max_uses' => 3,
            'effect' => 'waterfall', 'turns' => 3,
            'description' => 'While suffering a debuff, a wall of water stuns the opponent for 6 seconds before it moves. Up to 3 times a fight.'],
        '1803' => ['name' => 'Crystal Blade', 'school' => 'water', 'tier' => 3, 'requires' => '3820', 'kind' => 'strike', 'chance' => 26, 'power' => 0, 'chakra' => 260,
            'effect' => 'freeze', 'turns' => 11,
            'description' => 'Freezes the opponent instead of attacking: it cannot act or use fire jutsu, and the next hit on it deals double damage.'],
        '3821' => ['name' => 'Prayer', 'school' => 'water', 'tier' => 4, 'requires' => '1803', 'kind' => 'block', 'chance' => 28, 'power' => 0, 'chakra' => 80, 'max_uses' => 1,
            'effect' => 'invulnerable', 'turns' => 4,
            'description' => 'When attacked, becomes untouchable for 9 seconds. Once a fight.'],

        // Earth
        '1816' => ['name' => 'Great Mud River', 'school' => 'earth', 'tier' => 1, 'requires' => null, 'kind' => 'before_enemy', 'chance' => 32, 'power' => 0, 'chakra' => 190, 'max_uses' => 1,
            'effect' => 'slow', 'amount' => 50, 'turns' => 6,
            'description' => 'Turns the ground to mud before the opponent moves: 50% slower for 12 seconds. Once a fight.'],
        '3812' => ['name' => 'Detonating Clay', 'school' => 'earth', 'tier' => 2, 'requires' => null, 'kind' => 'prepare', 'chance' => 36, 'power' => 0, 'chakra' => 200, 'max_uses' => 1,
            'effect' => 'clay', 'turns' => 5,
            'description' => 'Wraps the opponent in clay that explodes after 11 seconds for all the damage it took meanwhile. Once a fight.'],
        '1815' => ['name' => 'Earth Prison', 'school' => 'earth', 'tier' => 3, 'requires' => '3812', 'kind' => 'follow_up', 'chance' => 18, 'power' => 0, 'chakra' => 0,
            'effect' => 'prison', 'amount' => 3, 'defense' => 50,
            'description' => 'After an attack, absorbs 3% of the opponent\'s chakra and lowers its defense by 50.'],
        '3806' => ['name' => 'Mud Wall', 'school' => 'earth', 'tier' => 4, 'requires' => '1815', 'kind' => 'reflect', 'chance' => 19, 'power' => 60, 'chakra' => 0,
            'description' => 'Raises a wall of mud that returns 60% of the damage taken.'],

        // Lightning
        '1802' => ['name' => 'Thunderfall', 'school' => 'lightning', 'tier' => 1, 'requires' => null, 'kind' => 'follow_up', 'chance' => 24, 'power' => 80, 'chakra' => 100,
            'effect' => 'slow', 'amount' => 50, 'turns' => 2,
            'description' => 'A bolt after a normal attack: 80% extra damage, paralyzing (50% slower) for 3.5 seconds.'],
        '1839' => ['name' => 'Static Field', 'school' => 'lightning', 'tier' => 2, 'requires' => '1802', 'kind' => 'before_enemy', 'chance' => 24, 'power' => 0, 'chakra' => 300,
            'effect' => 'shield', 'amount' => 15,
            'description' => 'A shield absorbing damage up to 15% of max health, refilled by a third of the user\'s lightning damage.'],
        '1825' => ['name' => 'Chidori', 'school' => 'lightning', 'tier' => 3, 'requires' => '1839', 'kind' => 'strike', 'chance' => 17, 'power' => 160, 'chakra' => 200,
            'effect' => 'slow', 'amount' => 50, 'turns' => 2,
            'description' => 'Lightning in the hand: 160% damage, paralyzing (50% slower) for 3.5 seconds.'],
        '3822' => ['name' => 'Flying Thunder God', 'school' => 'lightning', 'tier' => 4, 'requires' => '1825', 'kind' => 'battle', 'chance' => 100, 'power' => 0, 'chakra' => 380,
            'effect' => 'cloud', 'amount' => 7,
            'description' => 'A thunder cloud strikes a random side for 7% of its max health after each of the user\'s turns.'],

        // Wind
        '1826' => ['name' => 'Quickstep', 'school' => 'wind', 'tier' => 1, 'requires' => null, 'kind' => 'extra', 'chance' => 20, 'power' => 65, 'chakra' => 110,
            'description' => 'A burst of speed that lands an extra attack at 65% power before acting.'],
        '3811' => ['name' => 'Windstorm Array', 'school' => 'wind', 'tier' => 2, 'requires' => '1826', 'kind' => 'counter', 'chance' => 22, 'power' => 144, 'chakra' => 110,
            'description' => 'A sand storm strikes back when attacked: 144% damage.'],
        '1813' => ['name' => 'Gale Palm', 'school' => 'wind', 'tier' => 3, 'requires' => '3811', 'kind' => 'follow_up', 'chance' => 27, 'power' => 108, 'chakra' => 80,
            'description' => 'A sharp whirlwind after a normal attack: 108% extra damage.'],
        '1810' => ['name' => 'Rasengan', 'school' => 'wind', 'tier' => 4, 'requires' => '1813', 'kind' => 'strike', 'chance' => 26, 'power' => 180, 'chakra' => 140, 'self_damage' => 30,
            'description' => 'A spinning sphere of chakra: 180% damage, but 30% of it recoils on the user.'],

        // Body
        '1807' => ['name' => 'Lotus', 'school' => 'body', 'tier' => 1, 'requires' => null, 'kind' => 'strike', 'chance' => 23, 'power' => 250, 'chakra' => 0, 'self_stun' => 4,
            'description' => 'A flurry of blows at 250% damage that leaves the user exhausted for 8 seconds.'],
        '1822' => ['name' => 'Great Strength', 'school' => 'body', 'tier' => 2, 'requires' => '1807', 'kind' => 'strike', 'chance' => 15, 'power' => 100, 'chakra' => 0, 'max_hp_damage' => 7, 'stun' => 3, 'max_uses' => 3,
            'description' => 'A crushing leap: 100% damage plus 7% of the target\'s max health, stunning it for 6 seconds. Up to 3 times a fight.'],
        '1832' => ['name' => 'Eight Trigram Palm', 'school' => 'body', 'tier' => 3, 'requires' => '1822', 'kind' => 'strike', 'chance' => 28, 'power' => 150, 'chakra' => 0,
            'effect' => 'chakra_burn',
            'description' => 'Strikes the chakra points: 150% damage, and the opponent\'s jutsu cost twice the chakra for the rest of the fight.'],
        '3810' => ['name' => 'Eight Inner Gates', 'school' => 'body', 'tier' => 4, 'requires' => '1832', 'kind' => 'hurt', 'chance' => 100, 'power' => 0, 'chakra' => 0,
            'effect' => 'gates', 'amount' => 10, 'cooldown' => 5,
            'description' => 'When hurt, opens one of the eight gates (at most one every 10 seconds): 10% faster per gate.'],

        // Ninja tools
        '3825' => ['name' => 'Puppet', 'school' => 'tools', 'tier' => 1, 'requires' => null, 'kind' => 'follow_up', 'chance' => 33, 'power' => 48, 'chakra' => 0, 'max_uses' => 1,
            'effect' => 'poison', 'amount' => 3, 'turns' => 30,
            'description' => 'Poisoned claws after an attack: 48% extra damage, and the target loses 3% of its max health before each move for 60 seconds. Once a fight.'],
        '1828' => ['name' => 'Bomb', 'school' => 'tools', 'tier' => 2, 'requires' => '3825', 'kind' => 'strike', 'chance' => 33, 'power' => 210, 'chakra' => 0, 'backfire' => true,
            'description' => 'Throws a powerful bomb: 210% damage, but the longer the fight, the likelier it is kicked back.'],
        '3814' => ['name' => 'Liquor', 'school' => 'tools', 'tier' => 3, 'requires' => '1828', 'kind' => 'battle', 'chance' => 100, 'power' => 0, 'chakra' => 0,
            'effect' => 'drunk', 'amount' => 12, 'crit' => 3,
            'description' => 'Gets the opponent drunk: its hits miss 12% more, the user crits 3% more, and burns on it hurt half as much again.'],
        '3807' => ['name' => 'Snare', 'school' => 'tools', 'tier' => 4, 'requires' => '3814', 'kind' => 'prepare', 'chance' => 37, 'power' => 0, 'chakra' => 0,
            'effect' => 'snare', 'amount' => 6,
            'description' => 'A net before acting: the opponent\'s dodge drops by 6% for a turn.'],

        // Sealing
        '1805' => ['name' => 'Tailed Beast Heart', 'school' => 'seal', 'tier' => 1, 'requires' => null, 'kind' => 'prepare', 'chance' => 50, 'power' => 0, 'chakra' => 0,
            'effect' => 'cleanse',
            'description' => 'Before acting, removes one random debuff (not those that stop moving).'],
        '3819' => ['name' => 'Cursed Seal of Heaven', 'school' => 'seal', 'tier' => 2, 'requires' => '1805', 'kind' => 'battle', 'chance' => 100, 'power' => 0, 'chakra' => 10,
            'effect' => 'cursed_seal',
            'description' => '+0.5% attack for every 1% of health lost, and sealing jutsu on the user last half as long.'],
        '1823' => ['name' => 'Five Element Seal', 'school' => 'seal', 'tier' => 3, 'requires' => '3819', 'kind' => 'strike', 'chance' => 27, 'power' => 100, 'chakra' => 280, 'max_uses' => 1,
            'effect' => 'seal', 'turns' => 12,
            'description' => '100% damage, and the opponent cannot use the five element jutsu for 25 seconds. Once a fight.'],
        '1814' => ['name' => 'Dead Demon Seal', 'school' => 'seal', 'tier' => 4, 'requires' => '1823', 'kind' => 'strike', 'chance' => 27, 'power' => 100, 'chakra' => 310, 'max_uses' => 1,
            'effect' => 'dead_demon', 'amount' => 70, 'body' => 95, 'turns' => 12,
            'description' => '100% damage, and the opponent deals and heals 70% less (95% with body jutsu and Assassinate) for 25 seconds. Once a fight.'],

        // Illusion
        '1827' => ['name' => 'Assassinate', 'school' => 'illusion', 'tier' => 1, 'requires' => null, 'kind' => 'strike', 'chance' => 28, 'power' => 100, 'chakra' => 70, 'lifesteal' => 50,
            'effect' => 'purge',
            'description' => 'Strikes a vital point: 100% damage, half of it healed, and shakes off drunk, poison and burn.'],
        '3805' => ['name' => 'Sexy Technique', 'school' => 'illusion', 'tier' => 2, 'requires' => '1827', 'kind' => 'strike', 'chance' => 27, 'power' => 0, 'chakra' => 240, 'max_uses' => 1,
            'effect' => 'charm', 'turns' => 7,
            'description' => 'While the opponent has a buff, charms it so it cannot move for 15 seconds; lightning jutsu break the charm. Once a fight.'],
        '3826' => ['name' => 'Substitution', 'school' => 'illusion', 'tier' => 3, 'requires' => '3805', 'kind' => 'block', 'chance' => 17, 'power' => 0, 'chakra' => 0,
            'description' => 'Swaps places with a decoy and takes no damage from an attack.'],
        '3827' => ['name' => 'Death Mirage', 'school' => 'illusion', 'tier' => 4, 'requires' => '3826', 'kind' => 'follow_up', 'chance' => 28, 'power' => 0, 'chakra' => 90,
            'effect' => 'mirage', 'amount' => 6,
            'description' => 'After an attack, a nightmare: the opponent loses 6% of its max health and chakra each turn until it uses a chakra jutsu.'],

        // Healing
        '1829' => ['name' => 'Mystical Palm', 'school' => 'healing', 'tier' => 1, 'requires' => null, 'kind' => 'heal', 'chance' => 22, 'power' => 11, 'chakra' => 120, 'max_uses' => 3, 'uses_group' => 'medical',
            'description' => 'Heals 11% of lost health after being hurt and cures poison. Shares 3 uses a fight with Pre-Healing.'],
        '3802' => ['name' => 'Pre-Healing', 'school' => 'healing', 'tier' => 2, 'requires' => '1829', 'kind' => 'hurt', 'chance' => 31, 'power' => 0, 'chakra' => 60, 'max_uses' => 3, 'uses_group' => 'medical',
            'effect' => 'regen', 'amount' => 3, 'turns' => 2,
            'description' => 'After being hurt, heals 3% of max health before each of the next 2 moves and cures poison. Shares 3 uses a fight with Mystical Palm.'],
        '1830' => ['name' => 'Chakra Blade', 'school' => 'healing', 'tier' => 3, 'requires' => '3802', 'kind' => 'strike', 'chance' => 24, 'power' => 100, 'chakra' => 100,
            'effect' => 'chakra_cut', 'amount' => 15,
            'description' => 'A blade of chakra: 100% damage, and the opponent loses 15% of its max chakra.'],
        '3803' => ['name' => 'Creation Rebirth', 'school' => 'healing', 'tier' => 4, 'requires' => '1830', 'kind' => 'revive', 'chance' => 100, 'power' => 25, 'chakra' => 400, 'max_uses' => 1,
            'description' => 'Comes back from a knock-out with 25% health; less likely the longer the fight lasts, and not while unable to move.'],
    ],

];

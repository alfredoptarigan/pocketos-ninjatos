<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Creatable Avatars
    |--------------------------------------------------------------------------
    |
    | The avatars offered on the original Pockie Ninja create screen, as
    | "<sex>_<id>" keys (0 = male, 1 = female). Art for each key is produced
    | by tools/extract_character_assets.py into public/game-assets/characters.
    |
    */

    'avatars' => [
        '0_3', '0_9', '0_12', '0_14', '0_16', '0_17', '0_18', '0_19', '0_20',
        '1_26', '1_32', '1_33', '1_34', '1_36', '1_39', '1_61', '1_63', '1_65',
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

];

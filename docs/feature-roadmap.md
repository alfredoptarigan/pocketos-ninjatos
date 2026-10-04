# Feature roadmap

Every feature found in the original Pockie Ninja client backup
(`~/Privates/game-pockieninja/apache/source`), with its status in this rework.
The data source is listed for each feature so the next session knows where to start.

Legend: ✅ done · 🟡 partial · ⬜ not started

> The backup is the **client** side only: data tables (`binary/datatable/*.tab`),
> art, UI panels (`xml/bytexml`, `movieclip/ui`) and `language.lg`. Server logic
> (matchmaking, drop lists, quest scripts) is not included, so some rules have to
> be designed by us, as was done for tower drops, field rewards and shop prices.
> See `docs/asset-pipeline.md` for how to read every source format.

## Backlog: not done yet

Tick an item when it ships and update its row in the tables below.

**Character**

- [x] Titles (93 original titles; collection and achievement titles can be earned, one worn for its bonus)
- [x] Achievements (14 of the original 68 tracked: levels, gold spent, field bosses, sign-in days, points; the rest need arena, missions, pets, cards)
- [x] Honor and honor exchange (honor and medals from new tower floors and dungeon clears, 22 ranks, medals for EXP; the Equipment Set tab waits for equipment sets)
- [x] Costume change (outfits with grey/blue/orange rarity, Wardrobe page)
- [x] Avatar collection set bonuses and outfit upgrades (+1..+27, paid with outfit shards from duplicate draws)

**Combat and PvE**

- [x] Skill tree and skill upgrades (40 jutsu with all effects, 10 passives, skill points, +1..+12 upgrades, 3 loadout pages; scroll skills are not in the backup)
- [x] Ultimate jutsu (one per outfit, the original cinematics, a stronger one from +19; avatar bosses in the Training Tower use theirs)
- [x] Dungeons: 17 tollgates (trial, normal, hard), one battle per wave leader, daily runs
- [ ] Dungeon "Super" difficulty (needs the Abyss Pass item), weekly ranking, end-of-stage card flip
- [ ] Tailed Beast raids and ranking
- [ ] Public field fights (energy)
- [ ] Task bosses
- [ ] Ninja NPCs (friendship, gifts)
- [ ] Auto battle and auto challenge

**World**

- [ ] Search card peek minigame (searching itself is done)
- [ ] Mystic NPC and Mystic Shop

**Items and economy**

- [x] Multi-Sell from the Inventory (spare gear and whole item stacks)
- [x] Weapon classes (Blunt, Sharp, Fists; set by the outfit worn) and the weapon drawn in hand in battle
- [ ] Forge: enhance to +12
- [ ] Forge: crafting and compose
- [ ] Forge: gems and sockets
- [ ] Forge: identify and re-identify stats
- [ ] Forge: inscription
- [ ] Equipment set bonuses
- [ ] Player market
- [ ] Storage (depot)
- [ ] Item exchange
- [ ] Gift bags
- [x] Wish Pot for outfits (gift coupons, Lucky Pot building in Waterfall Village)
- [x] Lucky Pot after the original wishpot table: grey/blue/orange, +18 and +27 orange, Legend, Shippuden and S-rank (pick, and +27), Bankai title box; pet, tailed beast, forge and set pots show locked
- [x] Shippuden outfits (Kakuzu, Hidan, Deidara, Pain, Kisame, Konan, Sage Naruto, Hebi Sasuke, Suigetsu, Karin) and Little Jun, with their ultimates
- [ ] Pet and tailed beast pots (need pets and tailed beasts first)
- [ ] Collectible cards
- [ ] Premium shop and VIP

**Pets**

- [ ] Pets (items, EXP, pet home, pet slot in battle)

**Home**

- [ ] Home buildings and upgrades
- [ ] Crops
- [ ] Pet eggs
- [ ] "Slaves" (capture players as workers)
- [ ] Home task board, depot, friend visits

**PvP and competition**

- [ ] Arena / Leitai with ranking and arena shop
- [ ] Player duels
- [ ] World Race and World Match
- [ ] Nation / War Hall
- [ ] Leaderboards

**Social**

- [ ] Chat (World, Area, Whisper, System)
- [ ] Mail
- [ ] Friends and enemies
- [ ] Teams and parties
- [ ] Players in the area

**Quests and daily activities**

- [ ] Main and side quests (Mission Hall)
- [x] Daily sign-in with a 7-day streak (Gifts menu: EXP + gift coupons)
- [ ] Daily tasks
- [ ] Newcomer gifts and tutorial
- [ ] Seasonal events (Christmas, New Year)

**Infrastructure**

- [ ] Deploy for friends (server, PostgreSQL, HTTPS, assets extracted on the server)
- [ ] One-off fix on deploy: weapons worn before the class rule that do not fit the outfit's class

# Full feature list

## Character

| Status | Feature                                               | Original data                                                                                                                      |
| ------ | ----------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| ✅     | Create character, HUD, level and EXP, character panel | `creatrole`, `rolebase`, `roleinfo`                                                                                                |
| ✅     | Titles                                                | `title`, `titleitem`, panels `title*`                                                                                              |
| ✅     | Achievements                                          | `accomplishment`, panel `accomplishmentpop`                                                                                        |
| ✅     | Honor and honor exchange                              | `honorexchangeexp`, panels `myhonor*`, `honorexchange`                                                                             |
| ✅     | Avatar collection and costume change                  | `avatarcollect`, `avatarcollectleveladd`, `avataritem`, `recastavatar`, panels `avatarcollect*`, `avatartransition`, `avatarstock` |

## Combat and PvE

| Status | Feature                                                           | Original data                                                                                               |
| ------ | ----------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| ✅     | Training Tower (170 floors; bosses fight in their avatar art)     | `singlegatenpc`, `sgategetexp`, `stollgatebossinfo`                                                         |
| ✅     | Battle replay: jutsu effects, statuses, sound, HD fighters        | `effectconfig`, `movieclip/fighteffect`, `bitmap/icon/buff`                                                 |
| ✅     | Opponents fight with chakra and jutsu (our rule: no data)         | `config/skills.php` (`monsters`)                                                                            |
| ✅     | Skills: 40 jutsu, 10 passives, upgrades, loadouts, status effects | `clientskill`, `serverskillconfig`, `upskillcfg`, panels `skilltree`, `skilllearn`                          |
| ✅     | Ultimate jutsu                                                    | `clientskill` type 2, `serverskillconfig`, `fighteffect/bigeffect`, demo fights `dazhao`                    |
| 🟡     | Dungeons (9 tiered tollgates with sub-stages)                     | `tollgate`, `subtollgate`, `monstergroup`, `fightmonsterpoint`, `movieclip/scene/tollclone`, `ui/tollgate*` |
| ⬜     | Tailed Beast raids and ranking                                    | `tailbeastnpc`, `taildiffculty`, `tailheroreward`, `tailhonorprize`, `tailhonorreward`, panels `beast*`     |
| ⬜     | Public field fights (cost energy)                                 | `pubfightmonster`, `pubscenefightnpc`, `pubfightbonusrand`                                                  |
| ⬜     | Task bosses                                                       | `taskbossnpc`                                                                                               |
| ⬜     | Ninja NPCs (friendship, gifts)                                    | `ninjanpc`, panels `ninjanpc*`                                                                              |
| ⬜     | Auto battle and auto challenge                                    | `autoattack`, `autoattackcommon`, panels `autoattack*`, `autochallenge`                                     |

## World

| Status | Feature                                     | Original data                                        |
| ------ | ------------------------------------------- | ---------------------------------------------------- |
| ✅     | Villages and travel                         | `scene/maincity`, `scenecitybuild`                   |
| ✅     | World map                                   | `ui/sceneui/worldmap.swf`, `lg_Tip_WorldMap_*`       |
| ✅     | 24 hunting grounds with roaming monsters    | `normalnpc`, `taskbossnpc`, `scene/outcity`          |
| 🟡     | Searching: done; card peek minigame missing | `roleoutsearch` (peek costs), `ui/outsearchcard.swf` |
| ⬜     | Mystic NPC and Mystic Shop                  | `mysticnpc`, panels `mysticshop*`                    |

## Items and economy

| Status | Feature                                              | Original data                                                                                  |
| ------ | ---------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| ✅     | Inventory (bag + character panel), Pharmacy          | `pharmacyitem`                                                                                 |
| ✅     | Equipment (8 slots), Equipment Shop                  | `equipitem`                                                                                    |
| ✅     | Weapon classes and weapons held in battle            | `rolebase` (`Popsinger`), `equipitem` (`UsePopsinger`), `motion/weapon`, `weaponmotion`        |
| ⬜     | Forge: enhance to +12                                | `equipconsolidate`, `upgradecue`                                                               |
| ⬜     | Forge: crafting and compose                          | `equipcompose`, `composemethoditem`, `composetable`, `composetemp`, panels `equipcompose*`     |
| ⬜     | Forge: gems and sockets                              | `jewelitem`, `equipimbrate`, panel `removejew`                                                 |
| ⬜     | Forge: identify and re-identify stats                | `equipidentifypro`, `equipmagicpro`, panel `reidentifyresult`                                  |
| ⬜     | Forge: inscription                                   | `equipinscribe`                                                                                |
| ⬜     | Equipment set bonuses                                | `equipsuit`, `equipsuitpro`, panels `equipsuitshow*`                                           |
| ⬜     | Player market                                        | `martcollect`, `martcollectshow`, `martsellshow`, panels `mart*`                               |
| ⬜     | Storage (depot)                                      | panels `depot`, `exchangedepotlist`                                                            |
| ⬜     | Item exchange                                        | `itemexchange`, panel `itemtransform`                                                          |
| ⬜     | Gift bags                                            | `giftbagitem`, `giftid`, `normalgiftodd`, `specialgiftodd`                                     |
| ✅     | Lucky Pot and Wish Pot (pet/beast/forge pots locked) | `pottabvisible`, `wishpot`, panels `randpot*`, `wishpot*`                                      |
| ⬜     | Collectible cards (Card Hall, rooms, card link)      | `card_carditem`, `card_color`, `card_index_menu`, `cardprizeitem`, panels `card*`              |
| ⬜     | Premium shop and VIP                                 | `supershoptab`, `supershopsubtab`, `virtualshop`, `hotvirtualshop`, panels `supershop*`, `pay` |

## Pets

| Status | Feature                                        | Original data                                                                  |
| ------ | ---------------------------------------------- | ------------------------------------------------------------------------------ |
| ⬜     | Pets: items, EXP, pet home, pet slot in battle | `petitem`, `petgetexp`, `petreelitem`, `movieclip/home/pet`, panels `pethome*` |

Pets would also give a use to the "baby" result of searching (now the baby runs away).

## Home (large subsystem)

| Status | Feature                                     | Original data                                                                   |
| ------ | ------------------------------------------- | ------------------------------------------------------------------------------- |
| ⬜     | Home buildings and upgrades                 | `homebuildinglist`, `homebuildupgradecost`, `homecommontable`, `homeoutputinfo` |
| ⬜     | Crops                                       | `cropinfo`, `cropitem`, `weedinfo`                                              |
| ⬜     | Pet eggs                                    | `homeegg`, `incubatecfg`                                                        |
| ⬜     | "Slaves" (capture other players as workers) | `homeslaveevent`, panels `slave*`, `salve*`                                     |
| ⬜     | Home task board, depot, friend visits       | `hometaskboard`, `homedepot`, `homebedroomrecover`, panels `myhome*`            |

## PvP and competition

| Status | Feature                                                   | Original data                                                                       |
| ------ | --------------------------------------------------------- | ----------------------------------------------------------------------------------- |
| ⬜     | Arena / Leitai with ranking, reward boxes and arena shop  | `compete`, `competebonus`, `competenpc`, `competerank`, `leitai_*`, panels `arena*` |
| ⬜     | Player duels                                              | panels `playerpk*`                                                                  |
| ⬜     | World Race and World Match (event competitions with bets) | `worldmatch`, `worldracebuy`, `ui/worldrace*`, `ui/worldmatch*`                     |
| ⬜     | Nation / War Hall                                         | village building `nation`                                                           |
| ⬜     | Leaderboards                                              | `gametop`                                                                           |

## Social

| Status | Feature                             | Original data                                            |
| ------ | ----------------------------------- | -------------------------------------------------------- |
| ⬜     | Chat (World, Area, Whisper, System) | `chat`, `chatface`, `ui/sceneui/chat`, panels `whister*` |
| ⬜     | Mail                                | panels `mail*`                                           |
| ⬜     | Friends and enemies                 | `findfriend`, panels `society*`, `extendfriend*`         |
| ⬜     | Teams and parties                   | panels `team*`                                           |
| ⬜     | Players in the area                 | `ui/sceneui/outplayerlist`, panels `playerlist`          |

## Quests and daily activities

| Status | Feature                               | Original data                                                                                               |
| ------ | ------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| ⬜     | Main and side quests (Mission Hall)   | `task`, `subtask`, `taskitem`, `taskboardcfg`, `taskpichelp`, panels `task*`                                |
| 🟡     | Daily tasks (sign-in and streak done) | `mynotedayactive`, `mynotetarget`, `dayrewardlevel`, panels `daytask`, `everydaysignin`, `continuationgift` |
| ⬜     | Newcomer gifts and tutorial           | `newhandgift`, `newplayguide`, `freshmanmainguide`, `levelguide`                                            |
| ⬜     | Seasonal events (Christmas, New Year) | `christmas`, `snownpc`, `ui/christmasactivity`, `ui/newyeaychange`                                          |

## Suggested order (private server with friends)

1. **Chat, player list and friends**: makes the game feel multiplayer.
2. **Arena PvP and leaderboards**: the reason to play together.
3. **Quests** (Mission Hall and daily tasks): a guided progression path.
4. **Forge** (enhance, gems, set bonuses): needed for tower floors 160-170.
5. **Pets**: also makes the "baby" search result useful.

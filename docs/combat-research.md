# Combat research (from the Pockie Ninja backup)

Findings from decoding the original client data (`apache/source/binary/datatable/*.tab`,
zlib + AMF3, readable with `tools/amf3.py`) and the recorded fights in
`apache/source/fighttxt/*.fight`. No game art is reproduced here.

## How the original battle works

The server simulates the whole fight and sends the client a JSON log; the
client only replays it with animations. The `.fight` files are such logs:

```json
{
  "reason": 2,
  "role": {
    "0": {"Name": "...", "Level": 1, "Clothing": 12, "HP": 110, "MaxHP": 110, "MP": 56,
          "MinAtk": 20, "MaxAtk": 25, "Defense": 0, "DodgeMul": 5, "CritMul": 5,
          "HitMul": 0, "AtkTime": 50, "Offense": 1, "Weapon": "Icon_Weapon_Gloves1", "Skill": {}},
    "1": {"...": "opponent"}
  },
  "value": {
    "0": {"RoleIndex": 0, "Event": 1, "IsHit": 1},
    "1": {"RoleIndex": 0, "Event": 14, "SkillId": 1, "Damage": 24, "IsHit": 1, "TargetLastDamage": 24},
    "5": {"RoleIndex": 1, "Event": 14, "SkillId": 1816, "DecMP": 15, "addbuff": {"0": 11810}, "addbuffroleindex": {"0": 0}}
  }
}
```

Event codes seen across the 36 recordings (meaning inferred from their fields):

| Event | Fields | Likely meaning |
|---|---|---|
| 1 | — | actor's turn starts |
| 2 | — | actor's turn ends |
| 14 | SkillId, Damage, IsCrit, DecMP, IncHP, addbuff | own-action attack or skill (SkillId 1 = normal attack) |
| 7 | SkillId, Damage | reaction skill (fires when attacked, e.g. 3811 Sand Storm) |
| 6 | SkillId, Damage, IsCrit, TargetDecMP | skill that also drains target chakra |
| 3 | — | rare (2×), unknown |
| 20 | — | end of battle (once per recording) |

So the battle is **turn based and automatic**: attacker and defender alternate,
skills fire by chance at fixed moments, and the player only watches.

## Data tables

### Stages (single-player tollgates)

`tollgate` (20) → `subtollgate` (72, "beachheads") → `fightmonsterpoint` (359 waves,
linked list via Pre/NextMonsterGroupID) → `monstergroup` (MonsterConfig1-3 = up to 3
monsters per wave) → monster stats in `normalnpc`.

- `tollgate`: RequirLevel/MaxLevel, BeachheadAmount, MonsterGroupAmount, RewardExp,
  RewardMoney, RewardHono, BossName, TollGateMap (stage picture, e.g. `FristTollGatePic`
  in `movieclip/ui/*tollgatepic*.jpg`), TollCard*Money (end-of-stage card rewards).
- `subtollgate`: Recommendlevel ("15-16"), RewardMoney/RewardExp, BossName.
- `stollgatebossinfo`: boss floors with start/fail dialogue keys.
- `levelsceneid`, `pubfightmonster`, `pubscenefightnpc`, `pubfightbonusrand`: random
  field fights per level band (another mode).

### Monsters

`normalnpc` (1377 rows, `n300111`...): Level, MaxHP, MaxMP, MinAtk, MaxAtk, AtkTime,
Defense, CritMul, CritAttach (crit multiplier, e.g. 230 = 230%), HitMul, DodgeMul,
NeglectDefMul (armour break), PriorityMul (first strike), CounterMul, CounterBombMul,
VampiricHP, AbsorptionDamage, ReboundAttach (thorns), ParryMul, Pierce, Tough, NpcExp,
DropLibID. Names via `language.lg` (`lg_name_n...`, Chinese).

Example: first stage, first wave — `n300111` 雷云魔兵 (Thunder-cloud demon soldier),
Lv 11, HP 70, Atk 13-16, AtkTime 998.

`singlegatenpc` (170) has the same stat columns for another single-player mode.

### Player stats

`rolebase` (one row per avatar id): Popsinger (class), Strength, Agility, Stamina,
MaxHP, MaxMP, AtkTime, per-level growth (MaxHPUp, MaxMPUp, StrengthUp, AgilityUp,
StaminaUp) and aptitudes (MaxHPAdd, MaxMPAdd, StrAtkAdd, StrParryAdd, AgiAtkAdd,
DodgeAdd). The formulas turning these into HP/Atk live on the server and are not in
the backup; the recordings anchor level 1: HP 110, MP 56, Atk 20-25, Dodge 5, Crit 5
(avatar 46: HP 200, MP 120, Atk 37-42).

### Skills

- `clientskill` (339 skill lines) / `clientskillid` (676 levels): name/description keys,
  icon, max level, prerequisite, MPCostMul, Type (1 panel skill, 2 ultimate, 3 pet, 4 stage).
- **Mechanics are in the descriptions** (`language.lg`, `lg_SkillDes_<id>`), e.g.:
  - 1828 Bomb: 210% base attack; on own action; 33%; may be thrown back, more likely the
    longer the fight lasts.
  - 1822 Monstrous Strength: 100% base attack + 7% of target max HP; stuns 6 s; 15%.
  - 3811 Sand Storm: 144% base attack; when attacked; 22%.
  - 1816 Earth Flow River: enemy speed -50% for 12 s; before enemy acts; 32%; once per fight.
  - 3803 Creation Rebirth: revive with 25% max HP on death; 100%, falling as the fight goes on.
  - 19xx Ultimate (one per outfit): may instantly kill an opponent at low HP.
- `fightskill`, `serverskillconfig`, `clientskillconfig`: which motion/effect each skill
  plays (Action/BeAction motion ids, Splite).
- `fightingbuffconfig` (104) + `bufftips` + `effectconfig` (485): buffs/debuffs
  (paralysis, freeze, rage, burn, ...) with icons and visual effects.
- `upskillcfg`, `skillbook`, `skilleffect`, `skillaffect`: skill levelling and books.

### Progression

- `expmul` (level → reward multiplier), `sgategetexp` (169 steps, Exp/TotalExp),
  `trainexp`/`train` (training rooms).

## Art available

- Character motions: `movieclip/motion/people/people_<id>/motion_<sex>_<id>_<action>_role.swf`.
- Monster motions: `movieclip/motion/mob/{human,humanboss,inhuman,inhumanboss,searchboss}/n<id>/motion_<id>_<action>.swf`
  (171 monsters, ~4400 files; same bitmap-frame format as character motions).
- Monster faces: `bitmap/userfaceavatar/mapmob` (421).
- Battle backgrounds: `movieclip/scene/battle/*.swf`; stage pictures `movieclip/ui/*tollgatepic*.jpg`.
- Skill/hit effects: `movieclip/fighteffect` (556), ultimates in `source/dazhao`.
- Stage/arena music: `music/singlegate.mp3`, `music/compete1-4.mp3`.

## Not in the backup

- Server-side formulas (stat derivation, damage, hit/dodge rolls, exact skill timing).
  They must be designed; the recordings and descriptions give targets to calibrate to.
- Level-up experience table for players (only reward multipliers are present).

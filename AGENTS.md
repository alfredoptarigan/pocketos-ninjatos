# Pocketo Ninjatos: guide for AI agents

A learning/portfolio rework of the Flash MMO **Pockie Ninja**, played on a private
server with friends. It reuses the original art, music and data (extracted from a
backup) on a modern stack.

## Stack

- Laravel 13 + Inertia + React 19 + TypeScript (Laravel React starter kit, Fortify auth)
- PostgreSQL 18 locally (tests run on SQLite in memory)
- PixiJS v8 for the village and battle scenes
- Tailwind 4, shadcn/ui; `vite-plus` (`vp`) for lint/format/test
- Python 3 tools in `tools/` extract assets from the backup

## Read first

- `docs/asset-pipeline.md`: where the original assets come from, how to extract them,
  where they land, id cheat sheet, gotchas.
- `docs/combat-research.md`: what the original data says about battles, stages, skills.
- `config/game.php`: every gameplay number (avatars and their growth, combat formulas,
  villages, tower rewards). Formulas are ours; the original server code is not in the backup.

## Rules

- **Language:** all user-facing text, URLs and route names are English. The project owner
  chats in Indonesian.
- **Copyright:** never commit `public/game-assets/` or `database/data/` (gitignored).
  Commit only the extractors.
- **Git:** after every change, commit (conventional commits: `feat:`, `fix:`, `docs:`,
  `refactor:`, `chore:`) and push to `origin main` (SSH remote).
- **Tests first** for new backend behaviour (PHPUnit-style classes under `tests/Feature`
  and `tests/Unit`); one runnable check for new parsing logic in `tools/test_*.py`.
- Match surrounding code: small focused files, comments only for the non-obvious.

## Checks to run before committing

```bash
php artisan test
npm run types:check
npx vp check --fix            # lint + format
vendor/bin/pint               # PHP format
python3 -m unittest discover tools
npm run build                 # when frontend changed
```

After adding or changing routes: `php artisan wayfinder:generate --with-form`.

## Code map

| Area                                   | Where                                                                                                                                                                                                               |
| -------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Game rules and numbers                 | `config/game.php`                                                                                                                                                                                                   |
| Battle engine (pure, seeded RNG)       | `app/Game/BattleSimulator.php`, `Combatant.php`, `CombatStats.php`, `Leveling.php`, `Vitals.php`                                                                                                                    |
| Game actions (transactions, row locks) | `app/Actions/PurchaseItem.php`, `ChallengeTowerFloor.php`                                                                                                                                                           |
| HTTP                                   | `app/Http/Controllers/{Character,Village,Pharmacy,Bag,Tower,Battle}Controller.php`, `routes/web.php`                                                                                                                |
| Models                                 | `Character` (stats, vitals, `hud()`), `Item`, `InventoryItem`, `TowerFloor`, `Battle`                                                                                                                               |
| Seeders for extracted data             | `database/seeders/{ItemSeeder,TowerSeeder}.php`                                                                                                                                                                     |
| Shared Inertia props                   | `app/Http/Middleware/HandleInertiaRequests.php` (`character` = `Character::hud()`)                                                                                                                                  |
| Game pages                             | `resources/js/pages/{village,bag,tower,battle}.tsx`, `pages/buildings/*`, `pages/character/create.tsx`                                                                                                              |
| Full-screen game shell                 | `resources/js/layouts/game-layout.tsx` (HUD, menu, music); pages listed in `GAME_PAGES` in `resources/js/app.tsx`                                                                                                   |
| Original-skin components               | `game-window.tsx`, `game-menu.tsx`, `battle-hud.tsx`, `player-hud.tsx`, `village-backdrop.tsx`; CSS `.game-window/.game-button/.game-pill` in `resources/css/app.css`, skin URLs in `resources/views/app.blade.php` |
| Pixi                                   | `hooks/use-pixi-app.ts` (StrictMode-safe lifecycle), `components/village-canvas.tsx`, `game/village-scene.ts`, `components/battle-scene.tsx`, `game/battle/{fighter,replay,tween,types}.ts`                         |
| Asset extractors                       | `tools/` (see `docs/asset-pipeline.md`)                                                                                                                                                                             |

## How the game works today

1. Register/login (Fortify) → create a ninja (one per account; 18 original avatars).
2. Village: original background with clickable building cut-outs (pixel hit testing);
   the name banner is the travel menu (7 villages). Music per village.
3. Pharmacy (`salve` building): buy original potions with gold. Bag: view/use items.
4. Training Tower (`hall` building): 170 original floors. `POST /tower/{floor}/fight`
   simulates the battle on the server, stores the log in `battles`, redirects to
   `/battles/{id}` which replays it in Pixi with the original battle HUD.
   Rewards: exp/gold on first clear, reduced exp on replays; health/chakra fully restored
   after every battle.

## Not built yet (data exists in the backup)

Skills and buffs, equipment shop and forging, story stages (`tollgate`, level 11+),
missions, arena between players, pets, cards, housing/farming, chat.

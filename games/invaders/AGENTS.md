# invaders — notes for agents

This game is built with JumPHP. **Read `../../AGENTS.md` first** (rules, commands, API).

## Checklist

```bash
bin/jumphp test invaders                 # lint + phpstan + headless smoke test (must pass)
bin/jumphp shot invaders 60              # title screen -> games/invaders/var/screenshots/shot.png
JUMPHP_INPUT='[[5,"confirm"]]' bin/jumphp shot invaders 400 var/screenshots/play.png
JUMPHP_SCENE='App\Scene\PlayScene' JUMPHP_SCENE_PARAMS='{"demo":true,"keep":3}' bin/jumphp shot invaders 500   # next waves
JUMPHP_SCENE='App\Scene\PlayScene' JUMPHP_SCENE_PARAMS='{"lives":1}' bin/jumphp shot invaders 1500  # nobody plays: game over
JUMPHP_SCENE='App\Scene\PlayScene' JUMPHP_SCENE_PARAMS='{"demo":true}' bin/jumphp shot invaders 1200 # the saucer (15-25 s)
bin/jumphp build invaders && bin/jumphp webshot invaders 6 '[[1,"Enter"]]'   # browser (needs bin/jumphp engine:build)
```

## This game

- **What it is**: a fixed shooter, 224×256 (portrait). A fleet of 5 × 11 aliens marches side to side and
  steps down at each edge; shoot them all before they reach the cannon's row. 3 lives, extra life at 1500,
  best score in `var/options.json` key `best`. Each wave starts 8 px lower (up to 4 times), marches faster
  and drops more bombs.
- **Scenes**: `TitleScene` (score table + menu: PLAY, OPTIONS, QUIT on desktop; after 30 s idle it starts
  the demo) → `PlayScene`; overlays `PauseScene` (Escape / P), `GameOverScene` (params `score`, `wave`,
  `record`, `again`); `OptionsScene` pushed from the title (sound volume, language, fullscreen).
- **PlayScene params**: `wave` (first wave), `demo` (the computer plays, endless; any key → title),
  `keep` (keep only the last N invaders of each wave), `lives`.
  States: `ready` ("WAVE n", 2 s), `play`, `dying` (cannon explosion 1.6 s), `over`.
- **Entities** (`src/Entity`, plain arrays in the scene, no EntityManager / tiles):
  `Cannon`, `Invader` (moved by the scene: `march()` steps the whole fleet, interval ∝ invaders left),
  `Shot` (laser kind -1, bombs kind 0/1), `Saucer` (mystery ship, 50-300 points, 300 on shot 23 then every
  15th), `Bunker` (not an Entity: a 22×16 pixel grid eaten by `blast()` / `erase()`, drawn as runs).
  `Sprites` holds all the pixel art and creates the textures (`Sprites::load($sdl)`).
- **Physics**: `FixedStep(120)` for shots; laser vs bomb destroy each other; shots test shields pixel by pixel.
- **Controls**: Left / Right (A / D, pad, touch drag) move; Space / Up / W / Z / tap fire (one laser at a
  time); Escape / P pause.
- **Sounds**: all `Synth` (4-note march, laser, explosions, saucer warble). **Font**: built-in BitmapFont.
- **Non-obvious**: headless runs write `var/options.json` (best score): delete it for a clean title screen.

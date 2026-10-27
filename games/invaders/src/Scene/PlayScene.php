<?php

namespace App\Scene;

use App\Entity\Bunker;
use App\Entity\Cannon;
use App\Entity\Invader;
use App\Entity\Saucer;
use App\Entity\Shot;
use App\Entity\Sprites;
use JumPHP\Audio\Synth;
use JumPHP\Loop\FixedStep;
use JumPHP\Physics\Aabb;
use JumPHP\Renderer\Renderer;
use JumPHP\Scene\AbstractScene;
use JumPHP\Ui\Hud;
use JumPHP\Util\Lang;

/**
 * A game: 3 lives, a fleet of 55 aliens per wave. Params (enter):
 *   ['wave' => 1]      first wave (default 1)
 *   ['demo' => true]   the computer plays, endless (title attract mode, headless smoke test)
 *   ['keep' => 5]      only keep the last N invaders of each wave (tests: reach the next wave fast)
 *   ['lives' => 1]     lives at the start (tests: reach the game over fast)
 */
final class PlayScene extends AbstractScene
{
    public const MARGIN = 8;             // the fleet and the cannon stay this far from the sides
    private const COLUMNS = 11;
    private const ROWS = [0, 1, 1, 2, 2];   // invader kind of each row, top to bottom
    private const FLEET_TOP = 56;
    private const SAUCER_Y = 32;
    private const BUNKER_Y = 190;
    private const CANNON_Y = 216;
    private const GROUND_Y = 236;
    private const LIVES = 3;
    private const EXTRA_LIFE = 1500;
    private const READY_DELAY = 2.0;     // "WAVE n" before the fleet moves
    private const DEATH_DELAY = 1.6;     // the cannon explodes, then the next one comes
    private const MARCH = [98, 87, 78, 73];   // the four notes of the fleet's march

    private Cannon $cannon;
    /** @var Invader[] */
    private array $invaders = [];
    /** @var Shot[] */
    private array $shots = [];
    /** @var Bunker[] */
    private array $bunkers = [];
    private ?Saucer $saucer = null;
    /** @var array<int, array{x: float, y: float, t: float, text: string, color: array}> explosions and floating scores */
    private array $effects = [];
    private FixedStep $step;
    private string $state = 'ready';     // ready (wave n), play, dying (cannon hit), over
    private float $timer = 0.0;
    private float $time = 0.0;
    private float $marchTimer = 0.0;
    private int $marchDir = 1;
    private int $marchNote = 0;
    private int $frame = 0;
    private float $bombTimer = 0.0;
    private float $saucerTimer = 0.0;
    private float $saucerSound = 0.0;
    private int $shotsFired = 0;
    private int $wave = 1;
    private int $lives = self::LIVES;
    private int $score = 0;
    private bool $extraLife = false;
    private bool $demo = false;
    private int $keep = 0;
    private array $params = [];

    public function enter(array $params = []): void
    {
        $this->params = $params;
        $this->demo = !empty($params['demo']);
        $this->keep = (int)($params['keep'] ?? 0);
        $this->wave = max(1, (int)($params['wave'] ?? 1));
        $this->lives = max(1, (int)($params['lives'] ?? self::LIVES));
        $this->score = 0;
        $this->extraLife = false;
        $this->time = 0.0;
        $this->shotsFired = 0;
        $this->step = new FixedStep(120);   // laser 300 px/s: 2.5 px per step, no tunnelling through 1 px of shield

        Sprites::load($this->sdl());
        $this->cannon = new Cannon($this->sdl(), $this->width() / 2 - Cannon::WIDTH / 2, self::CANNON_Y, self::MARGIN, $this->width() - self::MARGIN);
        $this->buildBunkers();
        $this->buildFleet();

        $sound = $this->sound();
        if (!$sound->hasSfx('laser')) {
            $sound->synth('laser', Synth::sweep(1400, 500, 0.08, 'square', 0.3));
            $sound->synth('hit', Synth::noise(0.12, 0.4));
            $sound->synth('bunker', Synth::noise(0.04, 0.2));
            foreach (self::MARCH as $i => $hz) {
                $sound->synth('march' . $i, Synth::tone($hz, 0.07, 'square', 0.6));
            }
            $sound->synth('saucer', Synth::sweep(700, 900, 0.12, 'triangle', 0.25));
            $sound->synth('saucer_hit', Synth::sequence([[1200, 0.06], [900, 0.06], [1200, 0.06], [900, 0.06], [600, 0.12]], 'triangle'));
            $sound->synth('die', Synth::sweep(400, 40, 0.9, 'saw'));
            $sound->synth('wave', Synth::sequence([[523, 0.08], [659, 0.08], [784, 0.08], [1047, 0.25]]));
            $sound->synth('extra', Synth::sequence([[784, 0.08], [1047, 0.08], [1319, 0.15]]));
            $sound->synth('over', Synth::sequence([[392, 0.15], [330, 0.15], [262, 0.15], [196, 0.4]], 'triangle'));
        }
    }

    /** Four new shields, evenly spaced. */
    private function buildBunkers(): void
    {
        $this->bunkers = [];
        for ($i = 1; $i <= 4; $i++) {
            $this->bunkers[] = new Bunker((int)($this->width() * $i / 5 - Bunker::WIDTH / 2), self::BUNKER_Y);
        }
    }

    /** The fleet of the current wave: 5 rows of 11, a little lower every wave. */
    private function buildFleet(): void
    {
        $this->invaders = [];
        $this->shots = [];
        $this->saucer = null;
        $this->effects = [];
        $left = (int)(($this->width() - self::COLUMNS * Invader::CELL) / 2);
        $top = self::FLEET_TOP + min($this->wave - 1, 4) * 8;
        foreach (self::ROWS as $row => $kind) {
            for ($col = 0; $col < self::COLUMNS; $col++) {
                $this->invaders[] = new Invader($this->sdl(), $left + $col * Invader::CELL, $top + $row * Invader::CELL, $kind, $col);
            }
        }
        if ($this->keep > 0) {
            $this->invaders = array_slice($this->invaders, -$this->keep);
        }
        $this->marchDir = 1;
        $this->marchTimer = 0.0;
        $this->frame = 0;
        $this->bombTimer = 1.5;
        $this->saucerTimer = mt_rand(15, 25);
        $this->ready();
    }

    private function ready(): void
    {
        $this->state = 'ready';
        $this->timer = self::READY_DELAY;
    }

    public function update(float $dt): void
    {
        $this->time += $dt;
        if ($this->demo && ($this->actions()->isPressed('pause') || $this->actions()->isPressed('confirm') || $this->actions()->isPressed('fire'))) {
            $this->scenes()->switchTo(TitleScene::class);
            return;
        }
        if ($this->actions()->isPressed('pause') && $this->state !== 'over') {
            $this->scenes()->push(PauseScene::class);
            return;
        }

        foreach ($this->effects as $i => $effect) {
            $this->effects[$i]['t'] -= $dt;
            if ($this->effects[$i]['t'] <= 0) {
                unset($this->effects[$i]);
            }
        }

        if ($this->state === 'ready') {
            $this->controls($dt);
            $this->timer -= $dt;
            if ($this->timer <= 0) {
                $this->state = 'play';
            }
        } elseif ($this->state === 'play') {
            $this->controls($dt);
            $this->march($dt);
            $this->dropBombs($dt);
            $this->moveSaucer($dt);
            $this->step->run($dt, fn (float $h) => $this->simulate($h));
        } elseif ($this->state === 'dying') {
            $this->timer -= $dt;
            if ($this->timer <= 0) {
                $this->respawn();
            }
        } elseif ($this->state === 'over') {
            $this->timer -= $dt;
            if ($this->timer <= 0 && $this->demo) {
                $this->enter($this->params);     // the demo starts again by itself
            }
        }
    }

    // --- the player --------------------------------------------------------------------------------

    private function controls(float $dt): void
    {
        if ($this->demo) {
            $this->autopilot($dt);
            return;
        }
        $touch = $this->engine->input->getTouch();
        if ($touch->active) {
            $this->cannon->moveTowards($touch->x * $this->width(), $dt);
        } else {
            $this->cannon->move($this->actions()->axis('left', 'right'), $dt);
        }
        if ($this->state === 'play' && $this->actions()->isPressed('fire')) {
            $this->fire();
        }
    }

    /** Demo: dodge the bombs about to land, else get under the lowest invader and shoot it. */
    private function autopilot(float $dt): void
    {
        $cx = $this->cannon->centerX();
        foreach ($this->shots as $shot) {
            if (!$shot->isLaser() && $shot->getFloatY() > self::CANNON_Y - 50 && abs($shot->centerX() - $cx) < 12) {
                $away = $shot->centerX() < $cx ? 1 : -1;
                if ($cx + $away * 20 < self::MARGIN + 6 || $cx + $away * 20 > $this->width() - self::MARGIN - 6) {
                    $away = -$away;
                }
                $this->cannon->moveTowards($cx + $away * 20, $dt);
                return;
            }
        }
        $target = null;
        foreach ($this->invaders as $invader) {
            $score = $invader->getFloatY() * 2 - abs($invader->centerX() - $cx);
            if ($target === null || $score > $target[0]) {
                $target = [$score, $invader];
            }
        }
        if ($this->saucer !== null && abs($this->saucer->centerX() - $cx) < 30) {
            $aim = $this->saucer->centerX() + $this->saucer->getSpeedX() * 0.5;   // lead the saucer
        } else {
            $aim = $cx;
            if ($target !== null) {
                // lead the target: where the fleet will be when the laser gets there
                $flight = (self::CANNON_Y - $target[1]->getFloatY()) / Shot::LASER_SPEED;
                $aim = $target[1]->centerX() + $this->marchDir * 2 / $this->marchInterval() * $flight;
            }
        }
        $this->cannon->moveTowards($aim, $dt);
        if ($this->state === 'play' && abs($aim - $this->cannon->centerX()) < 3) {
            $this->fire();
        }
    }

    /** One laser on the screen at a time. */
    private function fire(): void
    {
        foreach ($this->shots as $shot) {
            if ($shot->isLaser()) {
                return;
            }
        }
        $this->shots[] = new Shot($this->sdl(), $this->cannon->muzzleX(), self::CANNON_Y - 4, -1, Shot::LASER_SPEED);
        $this->shotsFired++;
        $this->sound()->playSfx('laser');
    }

    private function respawn(): void
    {
        if ($this->lives <= 0) {
            $this->gameOver();
            return;
        }
        $this->cannon->setX($this->width() / 2 - Cannon::WIDTH / 2);
        $this->state = 'play';
    }

    // --- the fleet ---------------------------------------------------------------------------------

    /** Seconds between two steps of the fleet: the fewer invaders, the faster they march. */
    private function marchInterval(): float
    {
        $speedUp = max(0.55, 1.0 - 0.08 * ($this->wave - 1));
        return max(1 / 45, 0.8 * count($this->invaders) / 55 * $speedUp);
    }

    private function march(float $dt): void
    {
        $this->marchTimer -= $dt;
        if ($this->marchTimer > 0 || $this->invaders === []) {
            return;
        }
        $this->marchTimer = $this->marchInterval();
        $this->frame = 1 - $this->frame;
        $this->sound()->playSfx('march' . $this->marchNote);
        $this->marchNote = ($this->marchNote + 1) % count(self::MARCH);

        $dx = 2 * $this->marchDir;
        $edge = false;
        foreach ($this->invaders as $invader) {
            $x = $invader->getFloatX() + $dx;
            if ($x < self::MARGIN || $x + $invader->getWidth() > $this->width() - self::MARGIN) {
                $edge = true;
                break;
            }
        }
        $lowest = 0.0;
        foreach ($this->invaders as $invader) {
            $edge ? $invader->step(0, 8) : $invader->step($dx, 0);
            $lowest = max($lowest, $invader->getFloatY() + $invader->getHeight());
            foreach ($this->bunkers as $bunker) {
                $bunker->erase($invader->getBoundingBox());
            }
        }
        if ($edge) {
            $this->marchDir = -$this->marchDir;
        }
        if ($lowest >= self::CANNON_Y) {
            $this->landed();
        }
    }

    /** A random column fires (often the one over the cannon), from its lowest invader. */
    private function dropBombs(float $dt): void
    {
        $this->bombTimer -= $dt;
        if ($this->bombTimer > 0 || $this->invaders === []) {
            return;
        }
        $this->bombTimer = mt_rand(40, 120) / 100 / (1 + 0.15 * ($this->wave - 1));
        $bombs = 0;
        foreach ($this->shots as $shot) {
            $bombs += $shot->isLaser() ? 0 : 1;
        }
        if ($bombs >= min(4, 2 + intdiv($this->wave, 2))) {
            return;
        }
        $lowest = [];
        foreach ($this->invaders as $invader) {
            $col = $invader->column;
            if (!isset($lowest[$col]) || $invader->getFloatY() > $lowest[$col]->getFloatY()) {
                $lowest[$col] = $invader;
            }
        }
        $shooter = $lowest[array_rand($lowest)];
        if (mt_rand(0, 2) === 0) {
            foreach ($lowest as $invader) {
                if (abs($invader->centerX() - $this->cannon->centerX()) < Invader::CELL / 2) {
                    $shooter = $invader;
                }
            }
        }
        $speed = Shot::BOMB_SPEED + 6 * min($this->wave - 1, 6);
        $this->shots[] = new Shot($this->sdl(), $shooter->centerX(), $shooter->getFloatY() + 8, mt_rand(0, 1), $speed);
    }

    private function moveSaucer(float $dt): void
    {
        if ($this->saucer === null) {
            $this->saucerTimer -= $dt;
            if ($this->saucerTimer <= 0 && count($this->invaders) >= 8) {
                $dir = mt_rand(0, 1) ? 1 : -1;
                $this->saucer = new Saucer($this->sdl(), $dir > 0 ? -16 : $this->width(), self::SAUCER_Y, $dir);
                $this->saucerSound = 0.0;
            }
            return;
        }
        $this->saucerSound -= $dt;
        if ($this->saucerSound <= 0) {
            $this->saucerSound = 0.15;
            $this->sound()->playSfx('saucer');
        }
        if ($this->saucer->isGone($this->width())) {
            $this->saucer = null;
            $this->saucerTimer = mt_rand(20, 30);
        }
    }

    // --- physics -----------------------------------------------------------------------------------

    /** One step: move the shots and the saucer, then test what they hit. */
    private function simulate(float $h): void
    {
        if ($this->state !== 'play') {
            return;
        }
        $this->saucer?->advance($h);
        foreach ($this->shots as $i => $shot) {
            $shot->advance($h);
            if ($this->shotHits($shot)) {
                unset($this->shots[$i]);
            }
            if (!$this->playing()) {
                return;                          // the cannon was hit or the wave is over
            }
        }
        // a laser and a bomb that meet destroy each other
        foreach ($this->shots as $i => $laser) {
            if (!$laser->isLaser()) {
                continue;
            }
            foreach ($this->shots as $j => $bomb) {
                if (!$bomb->isLaser() && Aabb::intersects($laser->getBoundingBox(), $bomb->getBoundingBox())) {
                    $this->boom($bomb->centerX(), $bomb->centerY());
                    unset($this->shots[$i], $this->shots[$j]);
                    break;
                }
            }
        }
    }

    private function playing(): bool
    {
        return $this->state === 'play';
    }

    /** True when the shot is used up (it hit something or left the screen). */
    private function shotHits(Shot $shot): bool
    {
        $box = $shot->getBoundingBox();
        foreach ($this->bunkers as $bunker) {
            if (($pixel = $bunker->touch($box)) !== null) {
                $bunker->blast($pixel[0], $pixel[1], $shot->isLaser() ? 2 : 3);
                $this->sound()->playSfx('bunker');
                return true;
            }
        }
        if ($shot->isLaser()) {
            foreach ($this->invaders as $i => $invader) {
                if (Aabb::intersects($box, $invader->getBoundingBox())) {
                    $this->killInvader($i);
                    return true;
                }
            }
            if ($this->saucer !== null && Aabb::intersects($box, $this->saucer->getBoundingBox())) {
                $this->hitSaucer();
                return true;
            }
            if ($shot->getFloatY() < 24) {
                $this->boom($shot->centerX() - 0.5, 26, [255, 80, 80]);
                return true;
            }
            return false;
        }
        if (Aabb::intersects($box, $this->cannon->getBoundingBox())) {
            $this->cannonHit();
            return true;
        }
        if ($shot->getFloatY() + $shot->getHeight() >= self::GROUND_Y) {
            $this->boom($shot->centerX(), self::GROUND_Y - 3);
            return true;
        }
        return false;
    }

    private function killInvader(int $i): void
    {
        $invader = $this->invaders[$i];
        unset($this->invaders[$i]);
        $this->addScore($invader->points);
        $this->boom($invader->centerX(), $invader->centerY());
        $this->sound()->playSfx('hit');
        if ($this->invaders === []) {
            $this->waveCleared();
        }
    }

    private function hitSaucer(): void
    {
        // a fixed pattern on the shot count, like the old arcade machines: shot 23 then every 15th = 300
        $points = ($this->shotsFired === 23 || ($this->shotsFired > 23 && ($this->shotsFired - 23) % 15 === 0))
            ? 300 : Saucer::SCORES[$this->shotsFired % 3];
        $this->addScore($points);
        $this->effects[] = ['x' => $this->saucer->centerX(), 'y' => self::SAUCER_Y, 't' => 1.2, 'text' => (string)$points, 'color' => [255, 60, 60]];
        $this->saucer = null;
        $this->saucerTimer = mt_rand(20, 30);
        $this->sound()->playSfx('saucer_hit');
    }

    private function boom(float $cx, float $cy, array $color = [255, 255, 255]): void
    {
        $this->effects[] = ['x' => $cx, 'y' => $cy, 't' => 0.25, 'text' => '', 'color' => $color];
    }

    private function addScore(int $points): void
    {
        $this->score += $points;
        if (!$this->extraLife && $this->score >= self::EXTRA_LIFE) {
            $this->extraLife = true;
            $this->lives++;
            $this->sound()->playSfx('extra');
            $this->effects[] = ['x' => $this->width() / 2, 'y' => self::GROUND_Y + 4, 't' => 2.0, 'text' => Lang::t('EXTRA LIFE!'), 'color' => [60, 255, 60]];
        }
    }

    // --- end of a life, a wave, a game --------------------------------------------------------------

    private function cannonHit(): void
    {
        $this->lives--;
        $this->state = 'dying';
        $this->timer = self::DEATH_DELAY;
        $this->shots = [];
        $this->sound()->playSfx('die');
    }

    /** The fleet reached the ground: the game is lost at once. */
    private function landed(): void
    {
        $this->lives = 0;
        $this->effects[] = ['x' => $this->width() / 2, 'y' => self::GROUND_Y + 8, 't' => 3.0, 'text' => Lang::t('THEY HAVE LANDED!'), 'color' => [255, 60, 60]];
        $this->state = 'dying';
        $this->timer = self::DEATH_DELAY;
        $this->shots = [];
        $this->sound()->playSfx('die');
    }

    private function waveCleared(): void
    {
        $this->sound()->playSfx('wave');
        $this->wave++;
        $this->buildBunkers();
        $this->buildFleet();
    }

    private function gameOver(): void
    {
        $this->state = 'over';
        $this->timer = 3.0;
        $this->sound()->playSfx('over');
        if ($this->demo) {
            return;                              // restarts by itself (update)
        }
        $options = $this->engine->options;
        $best = (int)$options->get('best', 0);
        if ($this->score > $best) {
            $options->set('best', $this->score);
        }
        $this->scenes()->push(GameOverScene::class, [
            'score' => $this->score, 'wave' => $this->wave, 'record' => $this->score > $best, 'again' => $this->params,
        ]);
    }

    // --- drawing -----------------------------------------------------------------------------------

    public function render(Renderer $r): void
    {
        $w = $this->width();
        $font = $this->font();
        $r->clear([0, 0, 0]);

        // HUD: score, hi-score, wave
        $best = max($this->score, (int)$this->engine->options->get('best', 0));
        $font->draw($r, Lang::t('SCORE'), 8, 4, 1, [200, 200, 200]);
        $font->draw($r, sprintf('%05d', $this->score), 8, 14, 1, [255, 255, 255]);
        $font->draw($r, Lang::t('HI-SCORE'), $w - 8, 4, 1, [200, 200, 200], 'right');
        $font->draw($r, sprintf('%05d', $best), $w - 8, 14, 1, [255, 255, 255], 'right');
        $font->draw($r, $this->demo ? Lang::t('DEMO') : Lang::t('WAVE {n}', ['n' => $this->wave]), $w / 2, 9, 1, [255, 220, 120], 'center');

        $this->saucer?->draw($r);
        foreach ($this->invaders as $invader) {
            $invader->draw($r, $this->frame);
        }
        foreach ($this->bunkers as $bunker) {
            $bunker->draw($r);
        }
        foreach ($this->shots as $shot) {
            $shot->draw($r);
        }
        if ($this->state === 'dying') {
            $this->cannon->draw($r, self::DEATH_DELAY - $this->timer);
        } elseif ($this->state !== 'over') {
            $this->cannon->draw($r);
        }
        $boom = $this->sdl()->getTexture('boom')['texture'];
        foreach ($this->effects as $effect) {
            if ($effect['text'] !== '') {
                $font->draw($r, $effect['text'], $effect['x'], $effect['y'], 1, $effect['color'], 'center');
            } else {
                $r->copy($boom, null, $effect['x'] - 6, $effect['y'] - 3);
            }
        }

        // the ground and the spare cannons
        $r->fillRect(0, self::GROUND_Y, $w, 1, [60, 255, 60]);
        $font->draw($r, (string)$this->lives, 8, self::GROUND_Y + 8, 1, [255, 255, 255]);
        $cannon = $this->sdl()->getTexture('cannon')['texture'];
        for ($i = 0; $i < min($this->lives - 1, 6); $i++) {
            $r->copy($cannon, null, 20 + $i * 16, self::GROUND_Y + 7);
        }

        $cy = $this->height() * 0.58;
        if ($this->state === 'ready') {
            $font->draw($r, Lang::t('WAVE {n}', ['n' => $this->wave]), $w / 2, $cy - 12, 2, [255, 255, 255], 'center', [0, 0, 0]);
            if (Hud::blink($this->time)) {
                $font->draw($r, Lang::t('GET READY'), $w / 2, $cy + 6, 1, [200, 200, 200], 'center', [0, 0, 0]);
            }
        } elseif ($this->state === 'over' && $this->demo) {   // a player sees GameOverScene instead
            Hud::message($r, $font, Lang::t('GAME OVER'), $w / 2, $cy, 2);
        }
    }
}

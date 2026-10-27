<?php

namespace App\Scene;

use App\Entity\Sprites;
use JumPHP\Audio\Synth;
use JumPHP\Kernel\Platform;
use JumPHP\Renderer\Renderer;
use JumPHP\Ui\MenuScene;
use JumPHP\Util\Lang;

/**
 * Title screen: the menu, the score table, a starry sky. Left alone ATTRACT seconds, it plays the demo.
 */
final class TitleScene extends MenuScene
{
    private const ATTRACT = 30.0;
    protected string $title = 'INVADERS';
    protected float $titleScale = 4.0;
    protected array $titleColor = [60, 255, 60];
    protected float $menuTop = 0.66;
    private float $time = 0.0;

    public function enter(array $params = []): void
    {
        parent::enter($params);
        $this->time = 0.0;
        Sprites::load($this->sdl());
        if (!$this->sound()->hasSfx('menu_move')) {
            $this->sound()->synth('menu_move', Synth::tone(660, 0.04));
            $this->sound()->synth('menu_confirm', Synth::sequence([[660, 0.05], [990, 0.08]]));
        }
    }

    protected function items(): array
    {
        $items = [
            ['PLAY', null, fn () => $this->scenes()->switchTo(PlayScene::class, ['wave' => 1])],
            ['OPTIONS', null, fn () => $this->scenes()->push(OptionsScene::class)],
        ];
        if (Platform::isDesktop()) {
            $items[] = ['QUIT', null, fn () => $this->engine->quit()];
        }
        return $items;
    }

    public function update(float $dt): void
    {
        $this->time += $dt;
        if ($this->actions()->isPressed('up') || $this->actions()->isPressed('down')) {
            $this->time = 0.0;                   // someone is here: no demo
        }
        if ($this->time > self::ATTRACT) {
            $this->scenes()->switchTo(PlayScene::class, ['demo' => true]);
            return;
        }
        parent::update($dt);
    }

    protected function renderBackground(Renderer $r): void
    {
        $r->clear([0, 0, 0]);
        $w = $this->width();
        $h = $this->height();
        for ($i = 0; $i < 50; $i++) {
            $y = fmod($i * 53.0 + $this->time * (6 + $i % 4 * 5), $h);
            $c = 70 + ($i * 37) % 120;
            $r->fillRect(($i * 97) % $w, $y, 1, 1, [$c, $c, $c + 26]);
        }

        // the score table: one alien of each kind, the saucer
        $font = $this->font();
        $frame = (int)($this->time * 2) % 2 ? 'b' : 'a';
        $rows = [
            ['saucer', Lang::t('= ? MYSTERY'), [255, 60, 60]],
            ['invader0' . $frame, Lang::t('= {n} POINTS', ['n' => Sprites::INVADERS[0][2]]), [255, 90, 210]],
            ['invader1' . $frame, Lang::t('= {n} POINTS', ['n' => Sprites::INVADERS[1][2]]), [74, 224, 255]],
            ['invader2' . $frame, Lang::t('= {n} POINTS', ['n' => Sprites::INVADERS[2][2]]), [160, 255, 74]],
        ];
        foreach ($rows as $i => [$texture, $text, $color]) {
            $y = $h * 0.33 + $i * 16;
            $sprite = $this->sdl()->getTexture($texture);
            $r->copy($sprite['texture'], null, $w / 2 - 44 - $sprite['width'] / 2, $y);
            $font->draw($r, $text, $w / 2 - 30, $y, 1, $color);
        }

        $best = (int)$this->engine->options->get('best', 0);
        if ($best > 0) {
            $font->draw($r, Lang::t('HI-SCORE') . ' ' . sprintf('%05d', $best), $w / 2, $h * 0.27, 1, [255, 220, 120], 'center');
        }
        $font->draw($r, 'JUMPHP ' . date('Y'), $w / 2, $h - 12, 1, [70, 70, 90], 'center');
    }
}

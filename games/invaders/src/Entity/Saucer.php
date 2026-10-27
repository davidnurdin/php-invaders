<?php

namespace App\Entity;

use JumPHP\Entity\Entity;
use JumPHP\Renderer\Renderer;
use JumPHP\Renderer\Sdl;

/**
 * The mystery ship: crosses the top of the screen once, worth 50 to 300 points.
 */
final class Saucer extends Entity
{
    public const SPEED = 45.0;
    public const SCORES = [50, 100, 150, 300];

    public function __construct(private Sdl $sdl, float $x, float $y, int $dir)
    {
        $this->x = $x;
        $this->y = $y;
        $this->width = 16;
        $this->height = 7;
        $this->vx = $dir * self::SPEED;
    }

    public function advance(float $h): void
    {
        $this->x += $this->vx * $h;
    }

    /** Off the screen on the side it is heading to. */
    public function isGone(float $viewWidth): bool
    {
        return ($this->vx > 0 && $this->x > $viewWidth) || ($this->vx < 0 && $this->x < -$this->width);
    }

    public function draw(Renderer $r): void
    {
        $r->copy($this->sdl->getTexture('saucer')['texture'], null, $this->x, $this->y);
    }
}

<?php

namespace App\Entity;

use JumPHP\Entity\Entity;
use JumPHP\Renderer\Renderer;
use JumPHP\Renderer\Sdl;

/**
 * One alien of the fleet. It does not move by itself: the fleet (PlayScene) steps every invader at once.
 * Kind 0, 1, 2 = Sprites::INVADERS (top rows are worth more).
 */
final class Invader extends Entity
{
    public const CELL = 16;              // the fleet grid: one invader per 16 x 16 cell
    public readonly int $points;

    /** $x, $y: top-left of the grid cell; the sprite is centred in it. */
    public function __construct(private Sdl $sdl, float $x, float $y, public readonly int $kind, public readonly int $column)
    {
        $this->width = Sprites::INVADERS[$kind][0];
        $this->height = 8;
        $this->x = $x + (int)((self::CELL - $this->width) / 2);
        $this->y = $y;
        $this->points = Sprites::INVADERS[$kind][2];
    }

    public function step(float $dx, float $dy): void
    {
        $this->x += $dx;
        $this->y += $dy;
    }

    /** $frame: 0 / 1, the fleet's walking frame. */
    public function draw(Renderer $r, int $frame): void
    {
        $r->copy($this->sdl->getTexture('invader' . $this->kind . ($frame ? 'b' : 'a'))['texture'], null, $this->x, $this->y);
    }
}

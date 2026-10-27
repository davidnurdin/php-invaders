<?php

namespace App\Entity;

use JumPHP\Entity\Entity;
use JumPHP\Renderer\Renderer;
use JumPHP\Renderer\Sdl;
use JumPHP\Util\Math;

/**
 * The player's laser cannon: slides left and right along the ground, at most SPEED px/s.
 */
final class Cannon extends Entity
{
    public const WIDTH = 13;
    public const HEIGHT = 8;
    public const SPEED = 90.0;

    public function __construct(private Sdl $sdl, float $x, float $y, private float $left, private float $right)
    {
        $this->x = $x;
        $this->y = $y;
        $this->width = self::WIDTH;
        $this->height = self::HEIGHT;
    }

    /** $dir: -1 left, 0, 1 right (keys). */
    public function move(float $dir, float $dt): void
    {
        $this->setPosition($this->x + $dir * self::SPEED * $dt);
    }

    /** Towards a target x of the centre (computer, touch), at the cannon speed. */
    public function moveTowards(float $centerX, float $dt): void
    {
        $this->setPosition(Math::approach($this->x, $centerX - $this->width / 2, self::SPEED * $dt));
    }

    public function setPosition(float $x): void
    {
        $this->x = Math::clamp($x, $this->left, $this->right - $this->width);
    }

    /** Where the laser leaves the cannon. */
    public function muzzleX(): float
    {
        return $this->x + 6;
    }

    /** $hit: seconds since the cannon was hit (< 0: alive). */
    public function draw(Renderer $r, float $hit = -1.0): void
    {
        $name = $hit < 0 ? 'cannon' : 'cannon-hit' . ((int)($hit * 10) % 2);
        $r->copy($this->sdl->getTexture($name)['texture'], null, $this->x, $this->y);
    }
}

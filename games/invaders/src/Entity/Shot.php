<?php

namespace App\Entity;

use JumPHP\Entity\Entity;
use JumPHP\Renderer\Renderer;
use JumPHP\Renderer\Sdl;

/**
 * A projectile: the player's laser (kind -1, goes up) or an alien bomb (kind 0 / 1, goes down).
 */
final class Shot extends Entity
{
    public const LASER_SPEED = 300.0;
    public const BOMB_SPEED = 80.0;
    private float $time = 0.0;

    public function __construct(private Sdl $sdl, float $centerX, float $y, public readonly int $kind, float $speed)
    {
        $this->width = $kind < 0 ? 1 : 3;
        $this->height = $kind < 0 ? 4 : 7;
        $this->x = $centerX - (int)($this->width / 2);
        $this->y = $y;
        $this->vy = $kind < 0 ? -$speed : $speed;
    }

    public function isLaser(): bool
    {
        return $this->kind < 0;
    }

    public function advance(float $h): void
    {
        $this->y += $this->vy * $h;
        $this->time += $h;
    }

    public function draw(Renderer $r): void
    {
        if ($this->kind < 0) {
            $r->fillRect($this->x, $this->y, $this->width, $this->height, [255, 255, 255]);
            return;
        }
        $frame = (int)($this->time * 12) % 2;
        $r->copy($this->sdl->getTexture('bomb' . $this->kind . $frame)['texture'], null, $this->x, $this->y);
    }
}

<?php

namespace App\Entity;

use JumPHP\Renderer\Renderer;

/**
 * A shield: a grid of pixels that shots and bombs eat away. Not an Entity: it never moves, and the
 * collisions are tested pixel by pixel.
 */
final class Bunker
{
    public const WIDTH = 22;
    public const HEIGHT = 16;
    private const SHAPE = [
        '....##############....',
        '...################...',
        '..##################..',
        '.####################.',
        '######################',
        '######################',
        '######################',
        '######################',
        '######################',
        '######################',
        '######################',
        '######################',
        '#######........#######',
        '######..........######',
        '#####............#####',
        '#####............#####',
    ];

    /** @var array<int, array<int, bool>> [row][column] = solid */
    private array $pixels = [];

    public function __construct(public readonly int $x, public readonly int $y)
    {
        foreach (self::SHAPE as $row => $line) {
            for ($col = 0; $col < self::WIDTH; $col++) {
                $this->pixels[$row][$col] = $line[$col] === '#';
            }
        }
    }

    /**
     * The first solid pixel inside a box (view coordinates), or null.
     * @param array{x: float|int, y: float|int, width: int, height: int} $box
     * @return array{0: int, 1: int}|null [x, y] in view coordinates
     */
    public function touch(array $box): ?array
    {
        $x0 = max(0, (int)floor($box['x']) - $this->x);
        $x1 = min(self::WIDTH - 1, (int)floor($box['x'] + $box['width'] - 1) - $this->x);
        $y0 = max(0, (int)floor($box['y']) - $this->y);
        $y1 = min(self::HEIGHT - 1, (int)floor($box['y'] + $box['height'] - 1) - $this->y);
        for ($row = $y0; $row <= $y1; $row++) {
            for ($col = $x0; $col <= $x1; $col++) {
                if ($this->pixels[$row][$col]) {
                    return [$this->x + $col, $this->y + $row];
                }
            }
        }
        return null;
    }

    /** A blast at a point: removes a ragged round chunk. */
    public function blast(int $px, int $py, int $radius = 3): void
    {
        for ($dy = -$radius; $dy <= $radius; $dy++) {
            for ($dx = -$radius; $dx <= $radius; $dx++) {
                $d = $dx * $dx + $dy * $dy;
                if ($d <= 2 || ($d <= $radius * $radius && mt_rand(0, 1) === 1)) {
                    $this->clear($px + $dx - $this->x, $py + $dy - $this->y);
                }
            }
        }
    }

    /** Removes every pixel under a box (an invader walking through the shield). */
    public function erase(array $box): void
    {
        for ($py = (int)$box['y']; $py < $box['y'] + $box['height']; $py++) {
            for ($px = (int)$box['x']; $px < $box['x'] + $box['width']; $px++) {
                $this->clear($px - $this->x, $py - $this->y);
            }
        }
    }

    private function clear(int $col, int $row): void
    {
        if (isset($this->pixels[$row][$col])) {
            $this->pixels[$row][$col] = false;
        }
    }

    /** One rectangle per horizontal run of pixels. */
    public function draw(Renderer $r): void
    {
        foreach ($this->pixels as $row => $line) {
            $start = -1;
            for ($col = 0; $col <= self::WIDTH; $col++) {
                $solid = $col < self::WIDTH && $line[$col];
                if ($solid && $start < 0) {
                    $start = $col;
                } elseif (!$solid && $start >= 0) {
                    $r->fillRect($this->x + $start, $this->y + $row, $col - $start, 1, [60, 255, 60]);
                    $start = -1;
                }
            }
        }
    }
}

<?php

namespace App\Entity;

use JumPHP\Renderer\Sdl;

/**
 * All the pixel art of the game, drawn in code ('#' = colour, '.' = transparent). load() turns it into textures.
 */
final class Sprites
{
    /** Invader kinds (row of the fleet, top to bottom): [width, colour, points, frame A, frame B]. */
    public const INVADERS = [
        0 => [8, '#ff5ad2', 30, [
            '...##...',
            '..####..',
            '.######.',
            '##.##.##',
            '########',
            '..#..#..',
            '.#.##.#.',
            '#.#..#.#',
        ], [
            '...##...',
            '..####..',
            '.######.',
            '##.##.##',
            '########',
            '.#.##.#.',
            '#......#',
            '.#....#.',
        ]],
        1 => [11, '#4ae0ff', 20, [
            '..#.....#..',
            '...#...#...',
            '..#######..',
            '.##.###.##.',
            '###########',
            '#.#######.#',
            '#.#.....#.#',
            '...##.##...',
        ], [
            '..#.....#..',
            '#..#...#..#',
            '#.#######.#',
            '###.###.###',
            '###########',
            '.#########.',
            '..#.....#..',
            '.#.......#.',
        ]],
        2 => [12, '#a0ff4a', 10, [
            '....####....',
            '.##########.',
            '############',
            '###..##..###',
            '############',
            '...##..##...',
            '..##.##.##..',
            '##........##',
        ], [
            '....####....',
            '.##########.',
            '############',
            '###..##..###',
            '############',
            '..###..###..',
            '.##..##..##.',
            '..##....##..',
        ]],
    ];

    public const CANNON = [
        '......#......',
        '.....###.....',
        '.....###.....',
        '.###########.',
        '#############',
        '#############',
        '#############',
        '#############',
    ];

    public const CANNON_HIT = [[
        '..#......#...',
        '......#......',
        '...#.#...#...',
        '#...##.#.....',
        '...#######..#',
        '..#########..',
        '.###########.',
        '#############',
    ], [
        '#.....#.....#',
        '...#......#..',
        '.#...#..#....',
        '....#.##...#.',
        '#..#####.#...',
        '..######.##..',
        '.##########..',
        '############.',
    ]];

    public const SAUCER = [
        '.....######.....',
        '...##########...',
        '..############..',
        '.##.##.##.##.##.',
        '################',
        '..###..##..###..',
        '...#........#...',
    ];

    public const BOOM = [
        '#...#...#...#',
        '.#...#.#...#.',
        '..#.......#..',
        '##.........##',
        '..#.......#..',
        '.#...#.#...#.',
        '#...#...#...#',
    ];

    /** Two kinds of bombs, two frames each. */
    public const BOMBS = [[
        ['.#.', '#..', '.#.', '..#', '.#.', '#..', '.#.'],
        ['.#.', '..#', '.#.', '#..', '.#.', '..#', '.#.'],
    ], [
        ['.#.', '.#.', '.#.', '.#.', '.#.', '###', '.#.'],
        ['.#.', '###', '.#.', '.#.', '.#.', '.#.', '.#.'],
    ]];

    /** Creates every texture once (they live as long as the game). */
    public static function load(Sdl $sdl): void
    {
        if ($sdl->hasTexture('cannon')) {
            return;
        }
        foreach (self::INVADERS as $kind => [, $color, , $a, $b]) {
            $sdl->createTextureFromPixels("invader{$kind}a", $a, ['#' => $color]);
            $sdl->createTextureFromPixels("invader{$kind}b", $b, ['#' => $color]);
        }
        $sdl->createTextureFromPixels('cannon', self::CANNON, ['#' => '#3cff3c']);
        $sdl->createTextureFromPixels('cannon-hit0', self::CANNON_HIT[0], ['#' => '#3cff3c']);
        $sdl->createTextureFromPixels('cannon-hit1', self::CANNON_HIT[1], ['#' => '#3cff3c']);
        $sdl->createTextureFromPixels('saucer', self::SAUCER, ['#' => '#ff3c3c']);
        $sdl->createTextureFromPixels('boom', self::BOOM, ['#' => '#ffffff']);
        foreach (self::BOMBS as $kind => $frames) {
            foreach ($frames as $i => $rows) {
                $sdl->createTextureFromPixels("bomb{$kind}{$i}", $rows, ['#' => '#ffffff']);
            }
        }
    }
}

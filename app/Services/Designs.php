<?php

declare(strict_types=1);

namespace App\Services;

/**
 * How a page can look. A layout decides where the photos and the letter go; a theme decides the colours. Every layout
 * works with every theme. The CSS for both lives in public/assets/site.css (body[data-layout], body[data-theme]).
 */
final class Designs
{
    public const LAYOUTS = [
        'classic' => [
            'name' => 'Classic',
            'description' => 'Your photos with the letter scrolling beside them (below them on phones).',
        ],
        'cinematic' => [
            'name' => 'Cinematic',
            'description' => 'Full-screen photos; the letter floats over them like film subtitles.',
        ],
        'polaroid' => [
            'name' => 'Polaroid',
            'description' => 'Each photo in a white instant-photo frame, next to a handwritten letter.',
        ],
    ];

    /** colors: background, accent, highlight (used for the swatches in the dashboard) */
    public const THEMES = [
        'rose' => ['name' => 'Rose', 'colors' => ['#160a14', '#f2a7b8', '#f0cfa8']],
        'midnight' => ['name' => 'Midnight', 'colors' => ['#0a0f1f', '#a9b8ff', '#e8d6a8']],
        'sunset' => ['name' => 'Sunset', 'colors' => ['#1d0c08', '#ff9a76', '#ffd39a']],
        'blush' => ['name' => 'Blush', 'colors' => ['#fbf0ec', '#c2577a', '#b4874a']],
    ];

    public const DEFAULT_LAYOUT = 'classic';
    public const DEFAULT_THEME = 'rose';

    public static function layout(?string $key): string
    {
        return isset(self::LAYOUTS[$key]) ? (string) $key : self::DEFAULT_LAYOUT;
    }

    public static function theme(?string $key): string
    {
        return isset(self::THEMES[$key]) ? (string) $key : self::DEFAULT_THEME;
    }
}

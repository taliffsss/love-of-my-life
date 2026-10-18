<?php

declare(strict_types=1);

namespace App\Services;

/**
 * How a page can look: a template (stored in the page's `layout` column) and a colour theme. Every template works with
 * every theme.
 *
 * A template is a module of its own:
 *  - an entry in LAYOUTS (name, what it is, how it is played, its kind)
 *  - public/assets/templates/<key>.css, its look, using only the theme variables of themes.css
 *  - public/assets/templates/<key>.js, which registers window.LoveTemplates.<key> (see site.js for the contract)
 *  - resources/views/site/templates/<view>.naluz.php, its markup inside #show: "slideshow" for the three templates
 *    built on the shared slideshow, the template's own key for the interactive ones
 * The markup never holds the photos or the letter: they arrive after the answer (site.js fills the template).
 *
 * A theme is a block of CSS variables in public/assets/themes.css plus an entry in THEMES.
 */
final class Designs
{
    public const LAYOUTS = [
        'classic' => [
            'name' => 'Classic',
            'description' => 'Your photos with the letter scrolling beside them (below them on phones).',
            'play' => 'Swipe or tap the sides to change photos.',
            'kind' => 'slideshow',
        ],
        'cinematic' => [
            'name' => 'Cinematic',
            'description' => 'Full-screen photos; the letter floats over them like film subtitles.',
            'play' => 'Swipe through full-screen photos.',
            'kind' => 'slideshow',
        ],
        'polaroid' => [
            'name' => 'Polaroid',
            'description' => 'Each photo in a white instant-photo frame, next to a handwritten letter.',
            'play' => 'Swipe through the instant photos.',
            'kind' => 'slideshow',
        ],
        'envelope' => [
            'name' => 'Envelope',
            'description' => 'A sealed envelope. Breaking the heart seal slides out your letter, with the photos tucked inside.',
            'play' => 'Tap the seal to open it.',
            'kind' => 'interactive',
        ],
        'scratch' => [
            'name' => 'Scratch card',
            'description' => 'A card to scratch with a finger. Under the foil: your photo, then the letter.',
            'play' => 'Scratch the foil, or tap Reveal.',
            'kind' => 'interactive',
        ],
        'storybook' => [
            'name' => 'Storybook',
            'description' => 'A little book: every page you turn is a photo and a part of your letter.',
            'play' => 'Turn the pages.',
            'kind' => 'interactive',
        ],
        'memory' => [
            'name' => 'Memory game',
            'description' => 'Match the pairs of your photos; the letter opens when every pair is found.',
            'play' => 'Flip two cards at a time.',
            'kind' => 'interactive',
        ],
        'timeline' => [
            'name' => 'Timeline',
            'description' => 'Your story, one chapter per photo, each appearing as they scroll down.',
            'play' => 'Scroll down the story.',
            'kind' => 'interactive',
        ],
        'gift' => [
            'name' => 'Gift box',
            'description' => 'A wrapped present. Untie it and your photos rise out of the box with the letter.',
            'play' => 'Tap the box, then swipe the photos.',
            'kind' => 'interactive',
        ],
        'stars' => [
            'name' => 'Starry sky',
            'description' => 'Stars in the shape of a heart. Each one holds a photo and a part of the letter.',
            'play' => 'Tap every star.',
            'kind' => 'interactive',
        ],
    ];

    /** colors: background, accent, highlight (the swatches in the dashboard); scheme: light or dark background */
    public const THEMES = [
        'rose' => ['name' => 'Rose', 'colors' => ['#160a14', '#f2a7b8', '#f0cfa8'], 'scheme' => 'dark'],
        'midnight' => ['name' => 'Midnight', 'colors' => ['#0a0f1f', '#a9b8ff', '#e8d6a8'], 'scheme' => 'dark'],
        'sunset' => ['name' => 'Sunset', 'colors' => ['#1d0c08', '#ff9a76', '#ffd39a'], 'scheme' => 'dark'],
        'blush' => ['name' => 'Blush', 'colors' => ['#fbf0ec', '#c2577a', '#b4874a'], 'scheme' => 'light'],
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

    /** The partial under resources/views/site/templates/ that holds the template's markup. */
    public static function view(string $layout): string
    {
        $layout = self::layout($layout);
        return self::LAYOUTS[$layout]['kind'] === 'slideshow' ? 'slideshow' : $layout;
    }

    /** The template's own files, relative to public/. @return array{css:string,js:string} */
    public static function assets(string $layout): array
    {
        $layout = self::layout($layout);
        return ['css' => 'assets/templates/' . $layout . '.css', 'js' => 'assets/templates/' . $layout . '.js'];
    }
}

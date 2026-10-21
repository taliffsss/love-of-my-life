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
 * An occasion (Anniversary, Wedding, Boyfriend / Girlfriend, Birthday; stored in the page's nullable `occasion` column) dresses
 * every template in its own style: public/assets/occasions.css (the typeface and two ornaments, placed differently for
 * each template). Each occasion therefore offers the same templates as designs of its own, OCCASION_DESIGNS. The
 * template (layout) alone decides what a plan includes, so plans are unaffected by occasions.
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
        'halloween' => [
            'name' => 'Halloween',
            'description' => 'A haunted door under the moon. Knock three times; it creaks open on your photos and your letter.',
            'play' => 'Knock three times.',
            'kind' => 'interactive',
            'season' => true,
            'theme' => 'halloween',
        ],
        'christmas' => [
            'name' => 'Christmas',
            'description' => 'A snow globe to shake. The snow swirls, the tree lights up, and the gift inside is your letter.',
            'play' => 'Shake the globe.',
            'kind' => 'interactive',
            'season' => true,
            'theme' => 'christmas',
        ],
        'valentine' => [
            'name' => 'Valentine',
            'description' => 'Loves me, loves me not: pluck the petals one by one. The last one always says yes.',
            'play' => 'Pluck every petal.',
            'kind' => 'interactive',
            'season' => true,
            'theme' => 'valentine',
        ],
    ];

    /** colors: background, accent, highlight (the swatches in the dashboard); scheme: light or dark background */
    public const THEMES = [
        'rose' => ['name' => 'Rose', 'colors' => ['#160a14', '#f2a7b8', '#f0cfa8'], 'scheme' => 'dark'],
        'midnight' => ['name' => 'Midnight', 'colors' => ['#0a0f1f', '#a9b8ff', '#e8d6a8'], 'scheme' => 'dark'],
        'sunset' => ['name' => 'Sunset', 'colors' => ['#1d0c08', '#ff9a76', '#ffd39a'], 'scheme' => 'dark'],
        'blush' => ['name' => 'Blush', 'colors' => ['#fbf0ec', '#c2577a', '#b4874a'], 'scheme' => 'light'],
        // the colours of the seasonal templates, which always wear them (themeFor); not offered in the colour picker
        'halloween' => ['name' => 'Halloween', 'colors' => ['#130a1c', '#ff9a3d', '#ffd27a'], 'scheme' => 'dark', 'season' => true],
        'christmas' => ['name' => 'Christmas', 'colors' => ['#0b1a16', '#ff5a6e', '#ffe08a'], 'scheme' => 'dark', 'season' => true],
        'valentine' => ['name' => 'Valentine', 'colors' => ['#20060f', '#ff6f8e', '#ffc2cf'], 'scheme' => 'dark', 'season' => true],
    ];

    public const OCCASIONS = [
        'anniversary' => ['name' => 'Anniversary', 'blurb' => 'Another year of choosing each other.'],
        'wedding' => ['name' => 'Wedding', 'blurb' => 'For the day, the vows and the forever after.'],
        'partner' => ['name' => 'Boyfriend / Girlfriend', 'blurb' => 'For monthsaries, sweet surprises and just because.'],
        'birthday' => ['name' => 'Birthday', 'blurb' => 'Make their day with something made only for them.'],
        'invitation' => ['name' => 'Invitation', 'blurb' => 'Weddings, debuts, baptisms and parties: invite them in style.'],
    ];

    /** The designs of every occasion (one per template): layout key => [name, what makes it that occasion's]. */
    public const OCCASION_DESIGNS = [
        'anniversary' => [
            'classic' => ['Love Letter', 'Your letter beside the photos, with gold anniversary sparkles.'],
            'cinematic' => ['Our Movie', 'Full-screen photos under a gold-trimmed film strip of sparkles.'],
            'polaroid' => ['Memory Wall', 'Instant photos on a wall scattered with tiny sparkles.'],
            'envelope' => ['Sealed with a Kiss', 'A sealed envelope with sparkles on its corners.'],
            'scratch' => ['Anniversary Surprise', 'Scratch the foil to find the memory underneath.'],
            'storybook' => ['Our Story', 'A little book of your years, edged with sparkles.'],
            'memory' => ['Remember When', 'Match the pairs of your best moments.'],
            'timeline' => ['Years Together', 'Your story, chapter by chapter, down a sparkling edge.'],
            'gift' => ['Anniversary Gift', 'A wrapped present of photos and words.'],
            'stars' => ['Written in the Stars', 'A heart of stars, each holding a memory.'],
            'halloween' => ['Haunted Love', 'Our spooky-sweet night: knock, and the door opens on our memories.'],
            'christmas' => ['Winter Anniversary', 'A snow globe of the year we shared, lit up when you shake it.'],
            'valentine' => ['Loves Me Still', 'Pluck the petals: after all these years, the answer is still yes.'],
        ],
        'wedding' => [
            'classic' => ['Our Vows', 'The letter as your vows, in wedding script with rings.'],
            'cinematic' => ['Wedding Film', 'Full-screen photos with a ring-trimmed border.'],
            'polaroid' => ['Wedding Album', 'Instant photos in an album sprinkled with rings.'],
            'envelope' => ['The Invitation', 'A sealed wedding invitation with ring corners.'],
            'scratch' => ['Wedding Reveal', 'Scratch the foil to reveal the big news.'],
            'storybook' => ['Fairytale', 'A storybook of how it happened, edged with rings.'],
            'memory' => ['Guest Game', 'A matching game for the reception table.'],
            'timeline' => ['Journey to I Do', 'From hello to the altar, one chapter at a time.'],
            'gift' => ['Wedding Gift', 'A wrapped gift for the new husband or wife.'],
            'stars' => ['Meant to Be', 'Stars aligned in the shape of a heart.'],
            'halloween' => ['Spooky Vows', 'A haunted door that opens on your vows, for an autumn wedding.'],
            'christmas' => ['Winter Wedding', 'A snow globe for the winter day you said yes.'],
            'valentine' => ['Forever Yes', 'Loves me, loves me not, and then the vows.'],
        ],
        'partner' => [
            'classic' => ['Sweet Note', 'A handwritten-style letter with little hearts.'],
            'cinematic' => ['Us on Screen', 'Full-screen photos with a border of hearts.'],
            'polaroid' => ['Date Night', 'Instant photos from your best nights, with hearts.'],
            'envelope' => ['Secret Letter', 'A sealed letter with heart-shaped corners.'],
            'scratch' => ['Will You Be Mine', 'Scratch the card to find the question.'],
            'storybook' => ['Our Little Book', 'A tiny book about you two, with hearts along its edge.'],
            'memory' => ['Our Moments', 'Match the moments you shared.'],
            'timeline' => ['How We Began', 'The story of you two, down a line of hearts.'],
            'gift' => ['Just Because', 'A present for no reason except love.'],
            'stars' => ['My Person', 'A heart of stars, just for them.'],
            'halloween' => ['Trick or Treat', 'You are my treat. Knock to open it.'],
            'christmas' => ['Christmas With You', 'A snow globe for our first Christmas.'],
            'valentine' => ['Loves Me, Loves Me Not', 'Pluck the petals to find out how I feel.'],
        ],
        'birthday' => [
            'classic' => ['Birthday Wishes', 'Your letter beside the photos, with confetti.'],
            'cinematic' => ['Birthday Premiere', 'Full-screen photos under a confetti banner.'],
            'polaroid' => ['Photo Booth', 'Instant photos with confetti all over.'],
            'envelope' => ['Birthday Card', 'A sealed card with confetti on its corners.'],
            'scratch' => ['Scratch and Win', 'Scratch the foil to open the surprise.'],
            'storybook' => ['Story of You', 'A storybook of the birthday star.'],
            'memory' => ['Party Match-Up', 'Match the pairs of your favourite party moments.'],
            'timeline' => ['Year in Review', 'The year in moments, down a confetti edge.'],
            'gift' => ['Surprise Box', 'A box to open with photos inside.'],
            'stars' => ['Make a Wish', 'A star for every wish.'],
            'halloween' => ['Spooky Birthday', 'A haunted door for an October birthday.'],
            'christmas' => ['Christmas Birthday', 'A snow globe for a birthday in December.'],
            'valentine' => ['Birthday Petals', 'A petal for every wish, and the last one comes true.'],
        ],
        'invitation' => [
            'classic' => ['Debut', 'An 18th birthday invitation, the details beside the photos.'],
            'cinematic' => ['Save the Date', 'Full-screen photos and the date, like a film trailer.'],
            'polaroid' => ['Engagement Party', 'Instant photos of the couple, with the party details.'],
            'envelope' => ['Wedding Invitation', 'A sealed envelope: break the seal to read the invitation.'],
            'scratch' => ['Gender Reveal', 'Scratch the foil to find out, and when to celebrate.'],
            'storybook' => ['Christening', 'A little book that welcomes the baby, page by page.'],
            'memory' => ['Class Reunion', 'Match the old photos, then find out when and where.'],
            'timeline' => ['Wedding Program', 'The day in order: ceremony, reception and more.'],
            'gift' => ['Baby Shower', 'A wrapped surprise with the shower details inside.'],
            'stars' => ['Graduation Party', 'A sky of stars, each with a bit of the celebration.'],
            'halloween' => ['Halloween Party', 'Knock to find out where the party is.'],
            'christmas' => ['Christmas Party', 'Shake the globe to open the invitation.'],
            'valentine' => ['Valentine Party', 'Pluck the petals to see the party details.'],
        ],
    ];

    public const DEFAULT_LAYOUT = 'classic';
    public const DEFAULT_THEME = 'rose';

    public static function layout(?string $key): string
    {
        return isset(self::LAYOUTS[$key]) ? (string) $key : self::DEFAULT_LAYOUT;
    }

    /** The occasion key, or null when the page has none (or an unknown one). */
    public static function occasion(?string $key): ?string
    {
        return $key !== null && isset(self::OCCASIONS[$key]) ? $key : null;
    }

    public static function theme(?string $key): string
    {
        return isset(self::THEMES[$key]) ? (string) $key : self::DEFAULT_THEME;
    }

    /** The theme a page shows: a seasonal template always wears its own colours, any other uses the page's choice. */
    public static function themeFor(string $layout, ?string $theme): string
    {
        return self::LAYOUTS[self::layout($layout)]['theme'] ?? self::theme($theme);
    }

    /** The colour themes someone can pick: all but the seasonal ones. @return array<string,array<string,mixed>> */
    public static function pickableThemes(): array
    {
        return array_filter(self::THEMES, static fn (array $t): bool => empty($t['season']));
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

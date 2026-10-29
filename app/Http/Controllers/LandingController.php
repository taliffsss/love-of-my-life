<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Screen;
use App\Models\Page;
use App\Services\Designs;
use App\Services\PhotoAllowance;
use App\Services\PhotoStorage;
use App\Services\Plans;
use App\Services\Seasons;
use App\Services\Sites;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** The product's front door: what it is, the layouts, the prices, and a demo page anyone can open. */
final class LandingController
{
    public const DEMO_LETTER = [
        'If you are reading this, you found the little world I built for you. Every photo here is a day I would live again.',
        'Thank you for the slow mornings, the long drives and the way you laugh at my jokes even when they are not funny.',
        'I do not need anything grand. Just you, and more days like these. Happy monthsary, my love.',
    ];

    /** The sample invitation behind /demo?occasion=invitation: made-up hosts, so nobody's real details are shown. */
    public const DEMO_INVITATION = [
        'Together with our families, we joyfully invite you to celebrate the wedding of Ana and Miguel. It would mean the world to have you with us.',
        'Saturday, 12 December 2026, at 3:00 PM, at San Agustin Church, Manila. The reception follows at the Garden Hall.',
        'Kindly let us know by 1 December. Dress code: pastel formal. We cannot wait to celebrate with you.',
    ];

    public function index(ServerRequestInterface $request, Plans $plans, Sites $sites, PhotoAllowance $allowance, Seasons $seasons): ResponseInterface
    {
        // The look follows the season that is on (App\Services\Seasons::theme). Outside production (or with THEME_PREVIEW=true), ?theme=halloween|christmas|valentine|default previews any
        // of them as if its season were on: its template and its hero chip appear, and the others' do not.
        $theme = $seasons->look($request->getQueryParams());
        $previewing = $seasons->canPreview() && $theme === ($request->getQueryParams()['theme'] ?? null);
        $on = $previewing ? static fn (string $k): bool => $k === $theme : fn (string $k): bool => $seasons->isOpen($k);
        $offered = array_filter(Designs::LAYOUTS, static fn (array $l, string $k): bool => empty($l['season']) || $on($k), ARRAY_FILTER_USE_BOTH);
        return Screen::render('landing', [
            'theme' => $theme,
            'openSeasons' => array_values(array_filter(array_keys($offered), static fn (string $k): bool => !empty(Designs::LAYOUTS[$k]['season']))),
            'plans' => $plans->paid(),
            'freePhotos' => $allowance->freePhotos(),
            'pack' => $allowance->packOptions()[1],
            'maxPhoto' => PhotoStorage::megabytes((int) config('saas.max_upload_bytes')),
            'structuredData' => $this->structuredData(),
            'layouts' => $offered,
            'themes' => Designs::pickableThemes(),
            'domain' => $sites->domain(),
            'siteUrl' => rtrim((string) config('app.url'), '/') . '/',
        ]);
    }

    /** /invitations — the same product for weddings, debuts, christenings and other parties: what it does, the templates, the plans. */
    public function invitations(Plans $plans, Seasons $seasons, Sites $sites): ResponseInterface
    {
        $templates = [];
        foreach (Designs::OCCASION_DESIGNS['invitation'] as $key => [$name, $blurb]) {
            if (!empty(Designs::LAYOUTS[$key]['season']) && !$seasons->isOpen($key)) {
                continue; // a seasonal party template is only offered in its window
            }
            $first = $plans->plansWithTemplate($key)[0] ?? null;
            $templates[] = ['key' => $key, 'name' => $name, 'blurb' => $blurb, 'plan' => $first === null ? null : (string) $plans->get($first)['name'], 'seasonal' => !empty(Designs::LAYOUTS[$key]['season'])];
        }
        $siteUrl = rtrim((string) config('app.url'), '/') . '/';
        return Screen::render('invitations', [
            'theme' => 'default',
            'templates' => $templates,
            'plans' => $plans->paid(),
            'siteUrl' => $siteUrl,
            'pageUrl' => $siteUrl . 'invitations',
            'freePhotos' => app(PhotoAllowance::class)->freePhotos(),
            'domain' => $sites->domain(),
        ]);
    }

    /** Who the site is, for search engines: the organisation with its logo, and the site itself (schema.org, JSON-LD). */
    private function structuredData(): array
    {
        $site = rtrim((string) config('app.url'), '/') . '/';
        $brand = (string) config('saas.brand');
        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => $site . '#organization',
                    'name' => $brand,
                    'url' => $site,
                    // a square logo at least 112 px, as Google asks for; the file is public/icons/icon-512.png
                    'logo' => ['@type' => 'ImageObject', 'url' => $site . 'icons/icon-512.png', 'width' => 512, 'height' => 512],
                    'image' => $site . 'og-image.png',
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => $site . '#website',
                    'url' => $site,
                    'name' => $brand,
                    'description' => 'Make a private love page: your photos, your song and a letter, opened with a question only they can answer.',
                    'inLanguage' => 'en',
                    'publisher' => ['@id' => $site . '#organization'],
                ],
            ],
        ];
    }

    /** /demo?layout=cinematic&theme=midnight — the real page, with sample content; any answer opens it. */
    public function demo(ServerRequestInterface $request, SiteController $site): ResponseInterface
    {
        $query = $request->getQueryParams();
        // ?occasion=invitation shows the template as an invitation: addressed to the guests, no question, made-up hosts
        $invitation = ($query['occasion'] ?? null) === 'invitation';
        $recipient = $invitation ? 'Our family and friends' : 'Alex';
        $letter = $invitation ? self::DEMO_INVITATION : self::DEMO_LETTER;
        $page = new Page();
        $page->forceFill([
            'slug' => 'demo',
            'recipient' => $recipient,
            'letter' => $letter,
            'occasion' => $invitation ? 'invitation' : null,
            'song_id' => null,
            'gate_enabled' => !$invitation,
            'gate_eyebrow' => Page::DEFAULT_EYEBROW,
            'gate_question' => 'What do I always call you?',
            'layout' => Designs::layout(is_string($query['layout'] ?? null) ? $query['layout'] : null),
            'theme' => Designs::theme(is_string($query['theme'] ?? null) ? $query['theme'] : null),
            'plan' => 'forever',
            'searchable' => false,
        ]);
        return $site->render($page, 'demo', '', [
            'recipient' => $recipient,
            'occasion' => $page->occasion,
            'letter' => $letter,
            'photos' => ['/assets/demo/1.svg', '/assets/demo/2.svg', '/assets/demo/3.svg', '/assets/demo/4.svg'],
        ]);
    }
}

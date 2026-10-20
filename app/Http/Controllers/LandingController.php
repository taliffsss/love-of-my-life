<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Screen;
use App\Models\Page;
use App\Services\Designs;
use App\Services\PhotoAllowance;
use App\Services\PhotoStorage;
use App\Services\Plans;
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

    public function index(Plans $plans, Sites $sites, PhotoAllowance $allowance): ResponseInterface
    {
        return Screen::render('landing', [
            'plans' => $plans->paid(),
            'freePhotos' => $allowance->freePhotos(),
            'pack' => $allowance->packOptions()[1],
            'maxPhoto' => PhotoStorage::megabytes((int) config('saas.max_upload_bytes')),
            'structuredData' => $this->structuredData(),
            'layouts' => Designs::LAYOUTS,
            'themes' => Designs::THEMES,
            'domain' => $sites->domain(),
            'siteUrl' => rtrim((string) config('app.url'), '/') . '/',
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
        $page = new Page();
        $page->forceFill([
            'slug' => 'demo',
            'recipient' => 'Alex',
            'letter' => self::DEMO_LETTER,
            'song_id' => null,
            'gate_enabled' => true,
            'gate_eyebrow' => Page::DEFAULT_EYEBROW,
            'gate_question' => 'What do I always call you?',
            'layout' => Designs::layout(is_string($query['layout'] ?? null) ? $query['layout'] : null),
            'theme' => Designs::theme(is_string($query['theme'] ?? null) ? $query['theme'] : null),
            'plan' => 'forever',
            'searchable' => false,
        ]);
        return $site->render($page, 'demo', '', [
            'recipient' => 'Alex',
            'letter' => self::DEMO_LETTER,
            'photos' => ['/assets/demo/1.svg', '/assets/demo/2.svg', '/assets/demo/3.svg', '/assets/demo/4.svg'],
        ]);
    }
}

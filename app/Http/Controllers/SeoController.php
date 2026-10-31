<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\OccasionPages;
use Naluz\Http\Response;
use Psr\Http\Message\ResponseInterface;

/** robots.txt, sitemap and web manifest of the product itself (pages have their own, see SiteController). */
final class SeoController
{
    private function siteUrl(): string
    {
        return rtrim((string) config('app.url'), '/') . '/';
    }

    /**
     * Only the pages that ask to be indexed (their <meta name="robots"> says "index, follow"): the landing page, the invitations page, the occasion and audience pages and the two legal
     * documents. Sign-up, sign-in and the demo are noindex, and Google reports a noindex URL in a sitemap as an error.
     * `lastmod` is only given when it is true: the legal documents carry their own date; the other pages share `saas.seo.updated`, the day their words last changed (edit it when you change them).
     *
     * @return array<string,?string> path => lastmod (Y-m-d)
     */
    public static function indexablePages(): array
    {
        $legal = (string) config('saas.legal.updated', '');
        $legal = preg_match('/^\d{4}-\d{2}-\d{2}$/', $legal) === 1 ? $legal : null;
        $content = (string) config('saas.seo.updated', '');
        $content = preg_match('/^\d{4}-\d{2}-\d{2}$/', $content) === 1 ? $content : null;
        return ['' => $content, 'invitations' => $content] + array_fill_keys(OccasionPages::slugs(), $content) + ['terms' => $legal, 'privacy' => $legal];
    }

    public function sitemap(): ResponseInterface
    {
        $esc = static fn (string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $urls = '';
        foreach (self::indexablePages() as $path => $lastmod) {
            $urls .= '<url><loc>' . $esc($this->siteUrl() . $path) . '</loc>' . ($lastmod !== null ? '<lastmod>' . $lastmod . '</lastmod>' : '') . '</url>';
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . $urls . '</urlset>' . "\n";

        return Response::html($xml)
            ->withHeader('Content-Type', 'application/xml; charset=utf-8')
            ->withHeader('Cache-Control', 'public, max-age=3600');
    }

    public function robots(): ResponseInterface
    {
        if (config('app.env') !== 'production') {
            // a staging or local copy must never be indexed
            $body = "User-agent: *\nDisallow: /\n";
        } else {
            $body = "User-agent: *\n"
                . "Allow: /\n"
                // people's pages, accounts and the JSON endpoints stay out of search results; sign-up, sign-in and the demo are left
                // crawlable on purpose, so a crawler can read their noindex instead of guessing
                . "Disallow: /p/\n"
                . "Disallow: /dashboard\n"
                . "Disallow: /account\n"
                . "Disallow: /admin\n"
                . "Disallow: /api/\n"
                . "Disallow: /email/\n"
                . "Disallow: /forgot-password\n"
                . "Disallow: /reset-password\n"
                . "Disallow: /auth/\n"
                . "\nSitemap: " . $this->siteUrl() . "sitemap.xml\n";
        }

        return Response::html($body)
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withHeader('Cache-Control', 'public, max-age=3600');
    }

    /** A plain-text summary for AI assistants and crawlers (llmstxt.org): what the site is and its public pages. */
    public function llms(): ResponseInterface
    {
        $brand = (string) config('saas.brand');
        $lines = ["# {$brand}", '', '> Make a private love page: your photos, your song and a letter, opened with a question only they can answer. Free to build and preview; choose a plan to send it.', '', '## Pages', ''];
        foreach (OccasionPages::PAGES as $slug => $page) {
            $lines[] = '- [' . $page['name'] . '](' . $this->siteUrl() . $slug . '): ' . $page['description'];
        }
        $lines[] = '- [Invitations](' . $this->siteUrl() . 'invitations): Online invitations for weddings, debuts, christenings and parties.';

        return Response::html(implode("\n", $lines) . "\n")
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withHeader('Cache-Control', 'public, max-age=3600');
    }

    public function manifest(): ResponseInterface
    {
        $brand = (string) config('saas.brand');
        return Response::json([
            'name' => $brand,
            'short_name' => $brand,
            'description' => 'Make a private love page: your photos, your song and a letter, opened with a question only they can answer.',
            'id' => '/',
            'start_url' => '/',
            'scope' => '/',
            'categories' => ['lifestyle', 'social'],
            'display' => 'standalone',
            'background_color' => '#160a14',
            'theme_color' => '#1a0d17',
            'lang' => 'en',
            // "any" icons keep their rounded corners; "maskable" ones are full-bleed with the mark inside the safe zone, for the
            // shapes Android crops icons to (files made by marketing/brand/build_brand.py)
            'icons' => [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-maskable-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => '/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => '/favicon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'],
            ],
            'shortcuts' => [
                ['name' => 'Create a page', 'short_name' => 'New page', 'url' => '/register', 'icons' => [['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png']]],
                ['name' => 'Your pages', 'short_name' => 'Pages', 'url' => '/dashboard', 'icons' => [['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png']]],
            ],
        ])
            ->withHeader('Content-Type', 'application/manifest+json; charset=utf-8')
            ->withHeader('Cache-Control', 'public, max-age=86400');
    }
}

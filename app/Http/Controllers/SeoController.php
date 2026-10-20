<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Naluz\Http\Response;
use Psr\Http\Message\ResponseInterface;

/** robots.txt, sitemap and web manifest of the product itself (pages have their own, see SiteController). */
final class SeoController
{
    private function siteUrl(): string
    {
        return rtrim((string) config('app.url'), '/') . '/';
    }

    public function sitemap(): ResponseInterface
    {
        $esc = static fn (string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $urls = '';
        foreach (['' => '1.0', 'demo' => '0.6', 'register' => '0.5'] as $path => $priority) {
            $urls .= '<url><loc>' . $esc($this->siteUrl() . $path) . '</loc><changefreq>monthly</changefreq><priority>' . $priority . '</priority></url>';
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . $urls . '</urlset>' . "\n";

        return Response::html($xml)
            ->withHeader('Content-Type', 'application/xml; charset=utf-8')
            ->withHeader('Cache-Control', 'public, max-age=3600');
    }

    public function robots(): ResponseInterface
    {
        $body = "User-agent: *\n"
            . "Allow: /\n"
            // people's pages, the dashboard and JSON endpoints stay out of search results
            . "Disallow: /p/\n"
            . "Disallow: /dashboard\n"
            . "Disallow: /admin\n"
            . "Disallow: /api/\n"
            . 'Sitemap: ' . $this->siteUrl() . "sitemap.xml\n";

        return Response::html($body)
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

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
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#160a14',
            'theme_color' => '#1a0d17',
            'lang' => 'en',
            'icons' => [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ])
            ->withHeader('Content-Type', 'application/manifest+json; charset=utf-8')
            ->withHeader('Cache-Control', 'public, max-age=86400');
    }
}

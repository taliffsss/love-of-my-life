<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PhotoCatalog;
use App\Services\SeoData;
use Naluz\Http\Response;
use Psr\Http\Message\ResponseInterface;

final class SeoController
{
    private function siteUrl(): string
    {
        return rtrim((string) config('app.url'), '/') . '/';
    }

    /** Sitemap with the photos listed as images, so they can appear in image search. */
    public function sitemap(PhotoCatalog $catalog, SeoData $seo): ResponseInterface
    {
        $esc = static fn (string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $images = '';
        foreach ($catalog->absolute($this->siteUrl()) as $i => $photo) {
            $images .= '<image:image><image:loc>' . $esc($photo) . '</image:loc>'
                . '<image:caption>' . $esc('Photo memory ' . ($i + 1) . ' for ' . (string) config('site.name')) . '</image:caption></image:image>';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'
            . '<url><loc>' . $esc($this->siteUrl()) . '</loc>'
            . '<lastmod>' . $seo->lastModified()->format('Y-m-d') . '</lastmod>'
            . '<changefreq>monthly</changefreq><priority>1.0</priority>' . $images . '</url></urlset>' . "\n";

        return Response::html($xml)
            ->withHeader('Content-Type', 'application/xml; charset=utf-8')
            ->withHeader('Cache-Control', 'public, max-age=3600');
    }

    public function robots(): ResponseInterface
    {
        $body = "User-agent: *\n"
            . "Allow: /\n"
            // JSON endpoints and form actions have nothing to index
            . "Disallow: /api/\n"
            . "Disallow: /upload\n"
            . "Disallow: /song\n"
            . "Disallow: /letter\n"
            . 'Sitemap: ' . $this->siteUrl() . "sitemap.xml\n";

        return Response::html($body)
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withHeader('Cache-Control', 'public, max-age=3600');
    }

    /** Web app manifest: name, colours and icons used by browsers and "add to home screen". */
    public function manifest(): ResponseInterface
    {
        $name = (string) config('site.name');
        return Response::json([
            'name' => 'For ' . $name,
            'short_name' => (string) config('site.first_name'),
            'description' => 'A romantic photo slideshow and love letter.',
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

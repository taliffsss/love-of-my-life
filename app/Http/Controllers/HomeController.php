<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CarouselStore;
use Psr\Http\Message\ResponseInterface;

final class HomeController
{
    private const IMAGES = '/\.(jpe?g|png|webp|gif|avif)$/i';

    public function index(CarouselStore $store): ResponseInterface
    {
        $name = (string) config('site.name');
        $siteUrl = rtrim((string) config('app.url'), '/') . '/';
        $title = "For {$name} | A Romantic Photo Slideshow";
        $description = "A private romantic photo slideshow and love letter made with all my heart for {$name}.";

        return view('home', [
            'name' => $name,
            'firstName' => (string) config('site.first_name'),
            'letter' => (array) config('site.letter'),
            'photos' => array_merge($this->localPhotos(), $store->urls()),
            'placeholders' => ['#f9d5d3,#e8a5b5', '#fbe3d0,#f2b8a2', '#e6d3f0,#c9a6dc', '#ffe8e0,#f5b7c4'],
            'music' => $this->song(),
            'title' => $title,
            'description' => $description,
            'siteUrl' => $siteUrl,
            'ogImage' => $siteUrl . 'og-image.png',
            'jsonLd' => json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => $title,
                'description' => $description,
                'url' => $siteUrl,
                'inLanguage' => 'en',
                'image' => $siteUrl . 'og-image.png',
                'about' => ['@type' => 'Person', 'name' => $name],
            ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP),
        ]);
    }

    /** Images dropped into public/photos, in natural filename order. @return list<string> */
    private function localPhotos(): array
    {
        $files = array_filter(
            scandir(base_path('public/photos')) ?: [],
            static fn (string $f): bool => preg_match(self::IMAGES, $f) === 1,
        );
        natcasesort($files);
        return array_values(array_map(static fn (string $f): string => '/photos/' . rawurlencode($f), $files));
    }

    /** The first audio file in public/music, if any (otherwise the song streams from YouTube). */
    private function song(): string
    {
        foreach (scandir(base_path('public/music')) ?: [] as $f) {
            if (preg_match('/\.(mp3|m4a|ogg|wav)$/i', $f) === 1) {
                return '/music/' . rawurlencode($f);
            }
        }
        return '';
    }
}

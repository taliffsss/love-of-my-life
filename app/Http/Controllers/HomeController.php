<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\LetterStore;
use App\Services\PhotoCatalog;
use App\Services\SeoData;
use App\Services\SongStore;
use Psr\Http\Message\ResponseInterface;

final class HomeController
{
    public function index(PhotoCatalog $catalog, SongStore $songs, LetterStore $letters, SeoData $seo): ResponseInterface
    {
        $name = (string) config('site.name');
        $siteUrl = rtrim((string) config('app.url'), '/') . '/';
        $title = "For {$name} | A Romantic Photo Slideshow";
        $description = "A private romantic photo slideshow and love letter made with all my heart for {$name}: "
            . 'photos, a favourite song and a heartfelt message.';
        $ogImage = $siteUrl . 'og-image.png';
        $photos = $catalog->all();

        return view('home', [
            'name' => $name,
            'firstName' => (string) config('site.first_name'),
            'letter' => $letters->paragraphs(),
            // who the letter is for: "A letter for …" in the panel (changed from the message form)
            'recipient' => $letters->name(),
            'photos' => $photos,
            'placeholders' => ['#f9d5d3,#e8a5b5', '#fbe3d0,#f2b8a2', '#e6d3f0,#c9a6dc', '#ffe8e0,#f5b7c4'],
            // a song chosen from the page wins over a local file in public/music
            'music' => $songs->hasSaved() ? '' : $this->song(),
            'song' => $songs->get(),
            'assetVersion' => (string) max(
                (int) @filemtime(base_path('public/assets/app.js')),
                (int) @filemtime(base_path('public/assets/app.css')),
            ),
            'title' => $title,
            'description' => $description,
            'siteUrl' => $siteUrl,
            'ogImage' => $ogImage,
            'firstPhoto' => $photos[0] ?? '',
            'modified' => $seo->lastModified()->format('c'),
            'jsonLd' => $seo->jsonLd(
                ['name' => $name, 'siteUrl' => $siteUrl, 'title' => $title, 'description' => $description, 'ogImage' => $ogImage],
                $catalog->absolute($siteUrl),
            ),
        ]);
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

<?php

declare(strict_types=1);

namespace App\Services;

/** Every photo of the carousel, in display order: files in public/photos first, then the uploaded ones. */
final class PhotoCatalog
{
    private const IMAGES = '/\.(jpe?g|png|webp|gif|avif)$/i';

    public function __construct(private readonly CarouselStore $uploaded, private readonly string $photosDir)
    {
    }

    /** @return list<string> site-relative paths (/photos/01.jpg) and absolute URLs of uploaded photos */
    public function all(): array
    {
        return array_merge($this->local(), $this->uploaded->urls());
    }

    /** @return list<string> every photo as an absolute URL */
    public function absolute(string $siteUrl): array
    {
        $base = rtrim($siteUrl, '/');
        return array_map(
            static fn (string $url): string => preg_match('#^https?://#i', $url) === 1 ? $url : $base . $url,
            $this->all(),
        );
    }

    /** @return list<string> */
    private function local(): array
    {
        $files = array_filter(
            is_dir($this->photosDir) ? (scandir($this->photosDir) ?: []) : [],
            static fn (string $f): bool => preg_match(self::IMAGES, $f) === 1,
        );
        natcasesort($files);
        return array_values(array_map(static fn (string $f): string => '/photos/' . rawurlencode($f), $files));
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

/** Structured data (JSON-LD) and freshness dates for search engines. */
final class SeoData
{
    /** @param list<string> $watchedFiles files whose modification time means "the content changed" */
    public function __construct(private readonly array $watchedFiles)
    {
    }

    /** Last time the photos, message or song changed (falls back to the oldest watched file). */
    public function lastModified(): \DateTimeImmutable
    {
        $times = array_filter(array_map(static fn (string $f): int => is_file($f) ? (int) filemtime($f) : 0, $this->watchedFiles));
        return (new \DateTimeImmutable('@' . ($times === [] ? time() : max($times))))->setTimezone(new \DateTimeZone('UTC'));
    }

    /**
     * @param list<string> $photos absolute photo URLs
     * @param array{name:string,siteUrl:string,title:string,description:string,ogImage:string} $page
     */
    public function jsonLd(array $page, array $photos): string
    {
        $url = $page['siteUrl'];
        $modified = $this->lastModified()->format('c');
        $person = ['@type' => 'Person', '@id' => $url . '#person', 'name' => $page['name']];

        $graph = [
            [
                '@type' => 'WebSite',
                '@id' => $url . '#website',
                'url' => $url,
                'name' => 'For ' . $page['name'],
                'inLanguage' => 'en',
            ],
            [
                '@type' => 'WebPage',
                '@id' => $url . '#webpage',
                'url' => $url,
                'name' => $page['title'],
                'description' => $page['description'],
                'inLanguage' => 'en',
                'isPartOf' => ['@id' => $url . '#website'],
                'about' => ['@id' => $url . '#person'],
                'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => $page['ogImage'], 'width' => 1200, 'height' => 630],
                'dateModified' => $modified,
            ],
            $person,
        ];

        if ($photos !== []) {
            $n = 0;
            $graph[] = [
                '@type' => 'ImageGallery',
                '@id' => $url . '#gallery',
                'name' => 'Photo memories for ' . $page['name'],
                'url' => $url,
                'associatedMedia' => array_map(
                    static function (string $photo) use (&$n, $page): array {
                        $n++;
                        return ['@type' => 'ImageObject', 'contentUrl' => $photo, 'caption' => 'Photo memory ' . $n . ' for ' . $page['name']];
                    },
                    array_slice($photos, 0, 24),
                ),
            ];
        }

        return (string) json_encode(
            ['@context' => 'https://schema.org', '@graph' => $graph],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP,
        );
    }
}

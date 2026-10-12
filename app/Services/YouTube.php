<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

/** Understands YouTube links and looks up a video's title through YouTube's public oEmbed endpoint. */
final class YouTube
{
    private const HOSTS = [
        'youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com',
        'youtube-nocookie.com', 'www.youtube-nocookie.com', 'youtu.be',
    ];

    public function __construct(private readonly ?ClientInterface $http = null)
    {
    }

    /** Extracts the 11-character video id from a watch, youtu.be, embed, shorts, live or music link. */
    public static function parseId(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!in_array($host, self::HOSTS, true)) {
            return null;
        }

        $path = (string) ($parts['path'] ?? '');
        $id = null;
        if ($host === 'youtu.be') {
            $id = trim($path, '/');
        } elseif (preg_match('#^/(?:embed|shorts|live|v)/([^/?]+)#', $path, $m) === 1) {
            $id = $m[1];
        } else {
            parse_str((string) ($parts['query'] ?? ''), $query);
            $id = is_string($query['v'] ?? null) ? $query['v'] : null;
        }

        return $id !== null && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1 ? $id : null;
    }

    /**
     * @return array{title:string,artist:string}
     * @throws \InvalidArgumentException when YouTube says the video does not exist or cannot be played in an embed
     */
    public function lookup(string $id): array
    {
        $http = $this->http ?? new Client(['timeout' => 8, 'connect_timeout' => 4, 'http_errors' => false]);
        try {
            $response = $http->request('GET', 'https://www.youtube.com/oembed', [
                'query' => ['url' => 'https://www.youtube.com/watch?v=' . $id, 'format' => 'json'],
                'http_errors' => false,
            ]);
        } catch (\Throwable) {
            // YouTube is unreachable from this server: accept the link, with a generic title.
            return ['title' => 'Our song', 'artist' => ''];
        }

        $status = $response->getStatusCode();
        if (in_array($status, [400, 401, 403, 404], true)) {
            throw new \InvalidArgumentException('That video was not found or cannot be played in an embed.');
        }
        $data = json_decode((string) $response->getBody(), true);
        if ($status !== 200 || !is_array($data)) {
            return ['title' => 'Our song', 'artist' => ''];
        }
        return [
            'title' => trim((string) ($data['title'] ?? '')) ?: 'Our song',
            'artist' => trim((string) ($data['author_name'] ?? '')),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The list of every photo uploaded to Spaces, kept in a JSON file (storage/app/carousel.json). The carousel reads
 * it on each page load. On a fresh install the bundled database/carousel.seed.json is used until the first upload.
 */
final class CarouselStore
{
    public function __construct(private readonly string $file, private readonly string $seed = '')
    {
    }

    /** @return list<array{key:string,url:string}> */
    public function all(): array
    {
        $path = is_file($this->file) ? $this->file : $this->seed;
        if ($path === '' || !is_file($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);
        $photos = is_array($data) ? ($data['photos'] ?? []) : [];

        $out = [];
        foreach ($photos as $photo) {
            if (is_array($photo) && is_string($photo['key'] ?? null) && is_string($photo['url'] ?? null)) {
                $out[] = ['key' => $photo['key'], 'url' => $photo['url']];
            }
        }
        return $out;
    }

    /** @return list<string> public URLs, in upload order (oldest first, as stored) */
    public function urls(): array
    {
        return array_column($this->all(), 'url');
    }

    /** @return list<string> public URLs, newest upload first: the order the carousel shows them in */
    public function newestFirst(): array
    {
        return array_reverse($this->urls());
    }

    /** Appends one uploaded photo. Writes are locked and atomic so two uploads can't lose each other. */
    public function add(string $key, string $url): void
    {
        $dir = dirname($this->file);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create ' . $dir);
        }
        $lock = fopen($this->file . '.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new \RuntimeException('Cannot lock the carousel file.');
        }
        try {
            $photos = $this->all();
            $photos[] = ['key' => $key, 'url' => $url];
            $tmp = $this->file . '.tmp';
            file_put_contents($tmp, json_encode(['photos' => $photos], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
            rename($tmp, $this->file);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Puts photos at the beginning of the file, i.e. makes them the oldest, so everything uploaded later still shows
     * before them. Entries whose key is already listed are skipped. Returns how many were added.
     *
     * @param list<array{key:string,url:string}> $entries in storage order (oldest first)
     */
    public function prepend(array $entries): int
    {
        $dir = dirname($this->file);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create ' . $dir);
        }
        $lock = fopen($this->file . '.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new \RuntimeException('Cannot lock the carousel file.');
        }
        try {
            $current = $this->all();
            $known = array_column($current, 'key');
            $new = array_values(array_filter($entries, static fn (array $e): bool => !in_array($e['key'], $known, true)));
            if ($new === []) {
                return 0;
            }
            $tmp = $this->file . '.tmp';
            file_put_contents($tmp, json_encode(['photos' => array_merge($new, $current)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
            rename($tmp, $this->file);
            return count($new);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}

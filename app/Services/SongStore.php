<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The song that plays behind the slideshow. The choice made in the page is saved to storage/app/song.json and
 * becomes the default for everyone; until then the song from config/site.php is used.
 */
final class SongStore
{
    /** @param array{id:string,title:string,artist:string} $default */
    public function __construct(private readonly string $file, private readonly array $default)
    {
    }

    public function hasSaved(): bool
    {
        return $this->read() !== null;
    }

    /** @return array{id:string,title:string,artist:string} */
    public function get(): array
    {
        return $this->read() ?? $this->default;
    }

    public function set(string $id, string $title, string $artist): void
    {
        $dir = dirname($this->file);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create ' . $dir);
        }
        $tmp = $this->file . '.tmp';
        $json = json_encode(['id' => $id, 'title' => $title, 'artist' => $artist], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false || !rename($tmp, $this->file)) {
            throw new \RuntimeException('Cannot write ' . $this->file);
        }
    }

    /** @return array{id:string,title:string,artist:string}|null */
    private function read(): ?array
    {
        if (!is_file($this->file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($this->file), true);
        if (!is_array($data) || preg_match('/^[A-Za-z0-9_-]{11}$/', (string) ($data['id'] ?? '')) !== 1) {
            return null;
        }
        return ['id' => $data['id'], 'title' => (string) ($data['title'] ?? ''), 'artist' => (string) ($data['artist'] ?? '')];
    }
}

<?php

declare(strict_types=1);

namespace Naluz\Tests\Support;

use Naluz\Storage\Filesystem;

/** Stands in for DigitalOcean Spaces in tests: files live in an array, URLs point at a fake CDN. */
final class MemoryDisk implements Filesystem
{
    /** @var array<string,string> */
    public array $files = [];
    /** @var list<string> */
    public array $deleted = [];

    public function exists(string $path): bool
    {
        return isset($this->files[$path]);
    }

    public function get(string $path): string
    {
        return $this->files[$path];
    }

    public function put(string $path, mixed $contents): void
    {
        $this->files[$path] = (string) $contents;
    }

    public function delete(string $path): bool
    {
        $this->deleted[] = $path;
        unset($this->files[$path]);
        return true;
    }

    public function copy(string $from, string $to): void
    {
        $this->files[$to] = $this->files[$from];
    }

    public function move(string $from, string $to): void
    {
        $this->copy($from, $to);
        unset($this->files[$from]);
    }

    public function size(string $path): int
    {
        return strlen($this->files[$path]);
    }

    public function lastModified(string $path): int
    {
        return 0;
    }

    public function mimeType(string $path): string
    {
        return 'image/jpeg';
    }

    public function files(string $directory = ''): array
    {
        return array_keys($this->files);
    }

    public function directories(string $directory = ''): array
    {
        return [];
    }

    public function makeDirectory(string $path): void
    {
    }

    public function deleteDirectory(string $path): bool
    {
        return true;
    }

    public function url(string $path): string
    {
        return 'https://cdn.test/' . $path;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Page;

/** Rules for the dedicated address a Forever page can claim: <name>.<saas domain>. */
final class Subdomains
{
    public const MIN = 3;
    public const MAX = 30;

    /** @param list<string> $reserved */
    public function __construct(private readonly string $domain, private readonly array $reserved = [])
    {
    }

    /** Lower-cases and trims what was typed; "jinelin.example.com" (the whole address) becomes "jinelin". */
    public function normalise(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = (string) preg_replace('#^https?://#', '', $name);
        $name = rtrim($name, '/');
        $suffix = '.' . $this->domain;
        if ($this->domain !== '' && str_ends_with($name, $suffix)) {
            $name = substr($name, 0, -strlen($suffix));
        }
        return $name;
    }

    /** Why the (normalised) name cannot be used, or null when its format is fine. Availability is checked separately. */
    public function problem(string $name): ?string
    {
        $length = strlen($name);
        if ($length < self::MIN || $length > self::MAX) {
            return 'Use ' . self::MIN . ' to ' . self::MAX . ' characters.';
        }
        if (preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $name) !== 1) {
            return 'Use only lowercase letters, numbers and dashes, starting and ending with a letter or number.';
        }
        if (str_contains($name, '--')) {
            return 'Two dashes in a row are not allowed.';
        }
        if (in_array($name, $this->reserved, true)) {
            return 'That address is reserved. Try another one.';
        }
        return null;
    }

    public function isTaken(string $name, ?int $exceptPageId = null): bool
    {
        $query = Page::query()->withoutCache()->useWritePdo()->where('subdomain', $name);
        if ($exceptPageId !== null) {
            $query->where('id', '!=', $exceptPageId);
        }
        return $query->exists();
    }

    public function domain(): string
    {
        return $this->domain;
    }
}

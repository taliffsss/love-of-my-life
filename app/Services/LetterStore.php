<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The message (love letter) shown in the right-hand panel. A message saved from the page lives in
 * storage/app/letter.json and becomes the default for everyone; until then the text from config/site.php is used.
 */
final class LetterStore
{
    public const MAX_PARAGRAPHS = 40;
    public const MAX_CHARS = 8000;

    /** @param list<string> $default */
    public function __construct(private readonly string $file, private readonly array $default)
    {
    }

    /** @return list<string> */
    public function paragraphs(): array
    {
        return $this->read()['paragraphs'] ?? array_values(array_map('strval', $this->default));
    }

    /** @return list<array{key:string,at:string}> where every replaced message was archived */
    public function archive(): array
    {
        return $this->read()['archive'] ?? [];
    }

    /**
     * Splits what was typed into paragraphs (blank lines separate them; single line breaks become spaces).
     *
     * @return list<string>
     * @throws \InvalidArgumentException when empty or too long
     */
    public static function parse(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $paragraphs = [];
        foreach (preg_split('/\n\s*\n/', trim($text)) ?: [] as $chunk) {
            $chunk = trim((string) preg_replace('/\s*\n\s*/', ' ', $chunk));
            if ($chunk !== '') {
                $paragraphs[] = $chunk;
            }
        }
        if ($paragraphs === []) {
            throw new \InvalidArgumentException('The message is required.');
        }
        if (count($paragraphs) > self::MAX_PARAGRAPHS || mb_strlen(implode('', $paragraphs)) > self::MAX_CHARS) {
            throw new \InvalidArgumentException(
                'The message is too long (at most ' . self::MAX_PARAGRAPHS . ' paragraphs and ' . self::MAX_CHARS . ' characters).'
            );
        }
        return $paragraphs;
    }

    /** @param list<string> $paragraphs @param string $archivedKey where the replaced message was stored ('' if none) */
    public function set(array $paragraphs, string $archivedKey = '', ?\DateTimeImmutable $now = null): void
    {
        $archive = $this->archive();
        if ($archivedKey !== '') {
            $archive[] = ['key' => $archivedKey, 'at' => ($now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('c')];
        }

        $dir = dirname($this->file);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create ' . $dir);
        }
        $tmp = $this->file . '.tmp';
        $json = json_encode(
            ['paragraphs' => array_values($paragraphs), 'archive' => $archive],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false || !rename($tmp, $this->file)) {
            throw new \RuntimeException('Cannot write ' . $this->file);
        }
    }

    /** @return array{paragraphs?:list<string>,archive?:list<array{key:string,at:string}>}|null */
    private function read(): ?array
    {
        if (!is_file($this->file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($this->file), true);
        if (!is_array($data) || !is_array($data['paragraphs'] ?? null) || $data['paragraphs'] === []) {
            return null;
        }
        $archive = [];
        foreach ((array) ($data['archive'] ?? []) as $entry) {
            if (is_array($entry) && is_string($entry['key'] ?? null)) {
                $archive[] = ['key' => $entry['key'], 'at' => (string) ($entry['at'] ?? '')];
            }
        }
        return ['paragraphs' => array_values(array_map('strval', $data['paragraphs'])), 'archive' => $archive];
    }
}

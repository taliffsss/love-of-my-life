<?php

declare(strict_types=1);

namespace Naluz\Tests\Unit;

use App\Services\LetterStore;
use PHPUnit\Framework\TestCase;

final class LetterStoreTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/letter-' . bin2hex(random_bytes(4)) . '.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    public function testDefaultsUntilAMessageIsSaved(): void
    {
        $store = new LetterStore($this->file, ['One.', 'Two.'], 'Jinelin');
        $this->assertSame(['One.', 'Two.'], $store->paragraphs());
        $this->assertSame('Jinelin', $store->name());

        $store->set(['New.'], 'Maria', 'yam/messages/a.txt', new \DateTimeImmutable('2026-10-12T03:00:00+00:00'));

        $fresh = new LetterStore($this->file, ['One.', 'Two.'], 'Jinelin');
        $this->assertSame(['New.'], $fresh->paragraphs());
        $this->assertSame('Maria', $fresh->name(), 'the name is saved with the message');
        $this->assertSame([['key' => 'yam/messages/a.txt', 'at' => '2026-10-12T03:00:00+00:00']], $fresh->archive());
    }

    public function testEveryReplacementIsRemembered(): void
    {
        $store = new LetterStore($this->file, ['One.'], 'Jinelin');
        $store->set(['Two.'], 'Jinelin', 'a.txt');
        $store->set(['Three.'], 'Jinelin', 'b.txt');

        $this->assertSame(['a.txt', 'b.txt'], array_column($store->archive(), 'key'));
    }

    public function testParseSplitsOnBlankLines(): void
    {
        $this->assertSame(
            ['First line wraps here.', 'Second paragraph.'],
            LetterStore::parse("  First line\nwraps here.\r\n\r\n\n  Second paragraph.  \n")
        );
    }

    public function testParseRejectsEmptyAndTooLongMessages(): void
    {
        foreach (['', "  \n\n ", str_repeat('a', LetterStore::MAX_CHARS + 1), implode("\n\n", array_fill(0, LetterStore::MAX_PARAGRAPHS + 1, 'x'))] as $bad) {
            try {
                LetterStore::parse($bad);
                $this->fail('Expected the message to be rejected.');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testACorruptFileFallsBackToTheDefault(): void
    {
        file_put_contents($this->file, '{"paragraphs":[]}');
        $this->assertSame(['One.'], (new LetterStore($this->file, ['One.'], 'Jinelin'))->paragraphs());
    }

    public function testALetterSavedBeforeNamesExistedKeepsTheDefaultName(): void
    {
        file_put_contents($this->file, json_encode(['paragraphs' => ['Old text.'], 'archive' => []]));
        $store = new LetterStore($this->file, ['One.'], 'Jinelin');

        $this->assertSame(['Old text.'], $store->paragraphs());
        $this->assertSame('Jinelin', $store->name());
    }

    public function testParseNameTidiesTheInput(): void
    {
        $this->assertSame('Maria Clara', LetterStore::parseName("  Maria \t\n  Clara  "));
        $this->assertSame('Jinelin', LetterStore::parseName("Jinelin\x00\x07"));
    }

    public function testParseNameRejectsEmptyAndTooLongNames(): void
    {
        foreach (['', '   ', "\n\t", str_repeat('a', LetterStore::MAX_NAME + 1)] as $bad) {
            try {
                LetterStore::parseName($bad);
                $this->fail('Expected the name to be rejected: ' . json_encode($bad));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(str_repeat('a', LetterStore::MAX_NAME), LetterStore::parseName(str_repeat('a', LetterStore::MAX_NAME)));
    }
}

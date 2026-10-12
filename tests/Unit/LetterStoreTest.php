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
        $store = new LetterStore($this->file, ['One.', 'Two.']);
        $this->assertSame(['One.', 'Two.'], $store->paragraphs());

        $store->set(['New.'], 'yam/messages/a.txt', new \DateTimeImmutable('2026-10-12T03:00:00+00:00'));

        $fresh = new LetterStore($this->file, ['One.', 'Two.']);
        $this->assertSame(['New.'], $fresh->paragraphs());
        $this->assertSame([['key' => 'yam/messages/a.txt', 'at' => '2026-10-12T03:00:00+00:00']], $fresh->archive());
    }

    public function testEveryReplacementIsRemembered(): void
    {
        $store = new LetterStore($this->file, ['One.']);
        $store->set(['Two.'], 'a.txt');
        $store->set(['Three.'], 'b.txt');

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
        $this->assertSame(['One.'], (new LetterStore($this->file, ['One.']))->paragraphs());
    }
}

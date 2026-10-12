<?php

declare(strict_types=1);

namespace Naluz\Tests\Unit;

use App\Services\CarouselStore;
use PHPUnit\Framework\TestCase;

final class CarouselStoreTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/carousel-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    public function testReadsTheSeedUntilTheFirstUpload(): void
    {
        file_put_contents($this->dir . '/seed.json', json_encode(['photos' => [['key' => 'a', 'url' => 'https://cdn/a.jpg']]]));
        $store = new CarouselStore($this->dir . '/live.json', $this->dir . '/seed.json');

        $this->assertSame(['https://cdn/a.jpg'], $store->urls());

        $store->add('b', 'https://cdn/b.jpg');
        $this->assertSame(['https://cdn/a.jpg', 'https://cdn/b.jpg'], $store->urls());
        $this->assertFileExists($this->dir . '/live.json');
    }

    public function testNewestUploadComesFirstForDisplay(): void
    {
        $store = new CarouselStore($this->dir . '/live.json');
        $store->add('a', 'https://cdn/a.jpg');
        $store->add('b', 'https://cdn/b.jpg');
        $store->add('c', 'https://cdn/c.jpg');

        $this->assertSame(['https://cdn/a.jpg', 'https://cdn/b.jpg', 'https://cdn/c.jpg'], $store->urls(), 'the file keeps upload order');
        $this->assertSame(['https://cdn/c.jpg', 'https://cdn/b.jpg', 'https://cdn/a.jpg'], $store->newestFirst());
    }

    public function testMissingOrCorruptFilesGiveAnEmptyList(): void
    {
        $this->assertSame([], (new CarouselStore($this->dir . '/none.json'))->all());
        file_put_contents($this->dir . '/bad.json', '{nope');
        $this->assertSame([], (new CarouselStore($this->dir . '/bad.json'))->all());
    }

    public function testMalformedEntriesAreSkipped(): void
    {
        file_put_contents($this->dir . '/x.json', json_encode(['photos' => [['key' => 'a'], 'str', ['key' => 'k', 'url' => 'u']]]));
        $this->assertSame([['key' => 'k', 'url' => 'u']], (new CarouselStore($this->dir . '/x.json'))->all());
    }
}

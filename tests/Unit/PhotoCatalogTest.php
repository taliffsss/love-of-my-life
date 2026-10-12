<?php

declare(strict_types=1);

namespace Naluz\Tests\Unit;

use App\Services\CarouselStore;
use App\Services\PhotoCatalog;
use PHPUnit\Framework\TestCase;

final class PhotoCatalogTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/catalog-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/photos', 0777, true);
        foreach (['02.jpg', '10.jpg', '01.jpg', 'notes.txt'] as $f) {
            touch($this->dir . '/photos/' . $f);
        }
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/photos/*') ?: []);
        array_map('unlink', glob($this->dir . '/*.json*') ?: []);
        rmdir($this->dir . '/photos');
        rmdir($this->dir);
    }

    public function testNewUploadsComeFirstNewestOnTopThenThePhotosFolder(): void
    {
        $store = new CarouselStore($this->dir . '/live.json');
        $store->add('a', 'https://cdn.test/a.jpg');
        $store->add('b', 'https://cdn.test/b.jpg');
        $catalog = new PhotoCatalog($store, $this->dir . '/photos');

        $this->assertSame(
            ['https://cdn.test/b.jpg', 'https://cdn.test/a.jpg', '/photos/01.jpg', '/photos/02.jpg', '/photos/10.jpg'],
            $catalog->all(),
        );

        $store->add('c', 'https://cdn.test/c.jpg');
        $this->assertSame('https://cdn.test/c.jpg', $catalog->all()[0], 'a newer upload takes the very first place');
    }

    public function testWithoutUploadsThePhotosFolderIsUsedInNaturalOrder(): void
    {
        $catalog = new PhotoCatalog(new CarouselStore($this->dir . '/none.json'), $this->dir . '/photos');
        $this->assertSame(['/photos/01.jpg', '/photos/02.jpg', '/photos/10.jpg'], $catalog->all());
    }

    public function testAbsoluteKeepsTheSameOrder(): void
    {
        $store = new CarouselStore($this->dir . '/live.json');
        $store->add('a', 'https://cdn.test/a.jpg');
        $catalog = new PhotoCatalog($store, $this->dir . '/photos');

        $this->assertSame(
            ['https://cdn.test/a.jpg', 'https://love.test/photos/01.jpg', 'https://love.test/photos/02.jpg', 'https://love.test/photos/10.jpg'],
            $catalog->absolute('https://love.test/'),
        );
    }
}

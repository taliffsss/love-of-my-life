<?php

declare(strict_types=1);

namespace Naluz\Tests\Unit;

use App\Services\SongStore;
use PHPUnit\Framework\TestCase;

final class SongStoreTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/song-' . bin2hex(random_bytes(4)) . '.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    public function testDefaultsUntilASongIsSaved(): void
    {
        $default = ['id' => 'fu9yk7gCTbc', 'title' => 'Palagi', 'artist' => 'TJ Monterde'];
        $store = new SongStore($this->file, $default);

        $this->assertFalse($store->hasSaved());
        $this->assertSame($default, $store->get());

        $store->set('dQw4w9WgXcQ', 'New song', 'Someone');

        $this->assertTrue($store->hasSaved());
        $this->assertSame(['id' => 'dQw4w9WgXcQ', 'title' => 'New song', 'artist' => 'Someone'], (new SongStore($this->file, $default))->get());
    }

    public function testACorruptFileFallsBackToTheDefault(): void
    {
        file_put_contents($this->file, '{"id":"nope"}');
        $store = new SongStore($this->file, ['id' => 'fu9yk7gCTbc', 'title' => 'Palagi', 'artist' => '']);
        $this->assertFalse($store->hasSaved());
        $this->assertSame('Palagi', $store->get()['title']);
    }
}

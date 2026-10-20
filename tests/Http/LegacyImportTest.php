<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Models\Page;
use App\Models\Photo;
use App\Services\CarouselStore;
use App\Services\LegacyImporter;
use App\Services\LetterStore;
use App\Services\PhotoCatalog;
use App\Services\SongStore;
use Naluz\Database\Connection;
use Naluz\Security\Hasher;
use Naluz\Tests\TestCase;

/** The original single-page site becomes a Forever page on its own domain, with the same content. */
final class LegacyImportTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];
    private string $photosDir;

    private function tmp(string $name, string $contents = ''): string
    {
        $path = sys_get_temp_dir() . '/' . $name . '-' . bin2hex(random_bytes(4));
        file_put_contents($path, $contents);
        $this->files[] = $path;
        return $path;
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $f) {
            @unlink($f);
        }
        foreach (glob($this->photosDir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->photosDir);
        parent::tearDown();
    }

    private function importer(): LegacyImporter
    {
        $this->photosDir = sys_get_temp_dir() . '/photos-' . bin2hex(random_bytes(4));
        mkdir($this->photosDir);
        touch($this->photosDir . '/01.jpg');
        touch($this->photosDir . '/02.jpg');

        $carousel = new CarouselStore($this->tmp('carousel', (string) json_encode(['photos' => [
            ['key' => 'yam/old.jpg', 'url' => 'https://cdn.test/yam/old.jpg'],
            ['key' => 'yam/new.jpg', 'url' => 'https://cdn.test/yam/new.jpg'],
        ]])));
        $letters = new LetterStore($this->tmp('letter', (string) json_encode(['name' => 'Jinelin', 'paragraphs' => ['Hello.', 'Again.']])), ['Default.'], 'Default');
        $songs = new SongStore($this->tmp('song', (string) json_encode(['id' => 'fu9yk7gCTbc', 'title' => 'Palagi', 'artist' => 'TJ Monterde'])), []);

        return new LegacyImporter(
            $carousel,
            new PhotoCatalog($carousel, $this->photosDir),
            $letters,
            $songs,
            $this->app->make(Hasher::class),
            $this->app->make(Connection::class),
        );
    }

    public function testTheSiteBecomesAForeverPageOnItsDomain(): void
    {
        $owner = $this->makeUser();
        $page = $this->importer()->import($owner, 'Old-Site.Example.com', ' Yam ');

        $this->assertSame('old-site.example.com', $page->custom_domain);
        $this->assertSame('forever', $page->plan);
        $this->assertNull($page->expires_at);
        $this->assertTrue($page->isLive());
        $this->assertTrue($page->searchable);
        $this->assertSame('Jinelin', $page->recipient);
        $this->assertSame(['Hello.', 'Again.'], $page->paragraphs());
        $this->assertSame('fu9yk7gCTbc', $page->song_id);
        $this->assertTrue($page->hasGate());
        $this->assertTrue($this->app->make(Hasher::class)->check('yam', (string) $page->gate_password));

        $photos = Photo::query()->where('page_id', $page->id)->orderBy('position')->get()->all();
        $this->assertSame(
            ['https://cdn.test/yam/new.jpg', 'https://cdn.test/yam/old.jpg', '/photos/01.jpg', '/photos/02.jpg'],
            array_map(static fn (Photo $p): string => $p->url, $photos),
            'same order as the old carousel: newest upload first, then public/photos',
        );
        $this->assertSame('yam/new.jpg', $photos[0]->path, 'Spaces photos keep their key');
        $this->assertSame('spaces', $photos[0]->disk);
        $this->assertNull($photos[2]->disk, 'files shipped with the site are not storage the page owns');

        // and the old domain serves it, behind the same answer
        $this->assertStringContainsString('For Jinelin', (string) $this->get('https://old-site.example.com/')->getBody());
        $token = $this->csrf('https://old-site.example.com/');
        $open = $this->json('POST', 'https://old-site.example.com/unlock', ['answer' => 'YAM'], ['X-CSRF-TOKEN' => $token]);
        $this->assertSame(200, $open->getStatusCode());
        $this->assertCount(4, $this->decode($open)['photos']);
    }

    public function testItRefusesToImportTwice(): void
    {
        $owner = $this->makeUser();
        $importer = $this->importer();
        $importer->import($owner, 'old.example.com', 'yam');
        try {
            $importer->import($owner, 'old.example.com', 'yam');
            $this->fail('a second import must be refused');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('already served', $e->getMessage());
        }
        $this->assertSame(1, Page::query()->count());
    }
}

<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Services\CarouselStore;
use App\Services\LetterStore;
use App\Services\SongStore;
use App\Services\YouTube;
use Naluz\Storage\Filesystem;
use Naluz\Storage\StorageManager;
use Naluz\Tests\TestCase;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use Nyholm\Psr7\UploadedFile;

final class SiteTest extends TestCase
{
    private string $manifest;
    private string $songFile;
    private string $letterFile;

    protected function configOverrides(): array
    {
        return parent::configOverrides() + ['site.upload_key' => 'secret-key', 'app.url' => 'https://love.test'];
    }

    protected function migrate(): bool
    {
        return false;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->manifest = sys_get_temp_dir() . '/carousel-' . bin2hex(random_bytes(4)) . '.json';
        $this->app->instance(CarouselStore::class, new CarouselStore($this->manifest));
        $this->letterFile = sys_get_temp_dir() . '/letter-' . bin2hex(random_bytes(4)) . '.json';
        $this->app->instance(LetterStore::class, new LetterStore($this->letterFile, ['Old first.', 'Old second.']));
        $this->songFile = sys_get_temp_dir() . '/song-' . bin2hex(random_bytes(4)) . '.json';
        $this->app->instance(SongStore::class, new SongStore($this->songFile, ['id' => 'fu9yk7gCTbc', 'title' => 'Palagi', 'artist' => 'TJ Monterde']));
    }

    protected function tearDown(): void
    {
        @unlink($this->letterFile);
        @unlink($this->songFile);
        @unlink($this->manifest);
        @unlink($this->manifest . '.lock');
        parent::tearDown();
    }

    private function csrf(): string
    {
        $html = (string) $this->get('/')->getBody();
        $this->assertSame(1, preg_match('/name="csrf-token" content="([^"]+)"/', $html, $m));
        return $m[1];
    }

    private function memoryDisk(): Filesystem
    {
        return new class implements Filesystem {
            /** @var array<string,string> */
            public array $files = [];
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
                unset($this->files[$path]);
                return true;
            }
            public function copy(string $from, string $to): void
            {
            }
            public function move(string $from, string $to): void
            {
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
                return 'image/png';
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
        };
    }

    private function upload(string $token, string $key, ?string $bytes): \Psr\Http\Message\ResponseInterface
    {
        $request = (new ServerRequest('POST', '/upload', ['X-CSRF-TOKEN' => $token, 'Accept' => 'application/json']))
            ->withParsedBody(['key' => $key]);
        if ($bytes !== null) {
            $request = $request->withUploadedFiles(['photo' => new UploadedFile(Stream::create($bytes), strlen($bytes), UPLOAD_ERR_OK, 'x.png', 'image/png')]);
        }
        return $this->send($request);
    }

    private function png(): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGP4z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==');
    }

    public function testHomePageRendersTheSlideshowWithUploadedPhotos(): void
    {
        $this->app->make(CarouselStore::class)->add('yam/a.jpg', 'https://cdn.test/yam/a.jpg');

        $response = $this->get('/');
        $html = (string) $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('For Jinelin Basela | A Romantic Photo Slideshow', $html);
        $this->assertStringContainsString('src="https://cdn.test/yam/a.jpg"', $html);
        $this->assertStringContainsString('id="addBtn"', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://love.test/" />', $html);
        $this->assertStringContainsString('www.youtube.com', $response->getHeaderLine('Content-Security-Policy'));
    }

    public function testNothingShownToVisitorsMentionsTheStorageProvider(): void
    {
        $root = dirname(__DIR__, 2);
        $served = (string) $this->get('/')->getBody()
            . (string) file_get_contents($root . '/public/assets/app.js')
            . (string) file_get_contents($root . '/public/assets/app.css');

        $this->assertDoesNotMatchRegularExpression('/digital\s*ocean|spaces/i', preg_replace('/letter-spacing|white-space/i', '', $served));
    }

    public function testApiListsTheStoredPhotos(): void
    {
        $this->app->make(CarouselStore::class)->add('k', 'https://cdn.test/k.jpg');
        $this->assertSame(['photos' => [['key' => 'k', 'url' => 'https://cdn.test/k.jpg']]], $this->decode($this->get('/api/photos')));
    }

    public function testUploadNeedsACsrfToken(): void
    {
        $this->assertSame(419, $this->upload('bad', 'secret-key', $this->png())->getStatusCode());
    }

    public function testUploadRejectsTheWrongKey(): void
    {
        $response = $this->upload($this->csrf(), 'nope', $this->png());

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('Hint: your complete full name.', (string) $response->getBody());
    }

    public function testTheKeyIgnoresCaseAndExtraSpaces(): void
    {
        $this->app->make(\Naluz\Config\Repository::class)->set('site.upload_key', 'jinelin basela');
        $this->app->make(StorageManager::class)->extend('spaces', $this->memoryDisk());

        $this->assertSame(201, $this->upload($this->csrf(), '  Jinelin   BASELA ', $this->png())->getStatusCode());
    }

    public function testUploadStoresThePhotoAndRecordsItsUrl(): void
    {
        $disk = $this->memoryDisk();
        $this->app->make(StorageManager::class)->extend('spaces', $disk);

        $response = $this->upload($this->csrf(), 'secret-key', $this->png());
        $body = $this->decode($response);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertMatchesRegularExpression('#^https://cdn\.test/[0-9a-f]{32}\.png$#', $body['url']);
        $this->assertCount(1, $disk->files);
        $this->assertSame([$body['url']], $this->app->make(CarouselStore::class)->urls());
    }

    public function testSpacesFailureIsReportedAs502WithTheReason(): void
    {
        $disk = new class implements Filesystem {
            public function exists(string $path): bool
            {
                return false;
            }
            public function get(string $path): string
            {
                return '';
            }
            public function put(string $path, mixed $contents): void
            {
                throw new \Naluz\Storage\StorageException('Storage rejected the upload (HTTP 403, AccessDenied).');
            }
            public function delete(string $path): bool
            {
                return true;
            }
            public function copy(string $from, string $to): void
            {
            }
            public function move(string $from, string $to): void
            {
            }
            public function size(string $path): int
            {
                return 0;
            }
            public function lastModified(string $path): int
            {
                return 0;
            }
            public function mimeType(string $path): string
            {
                return '';
            }
            public function files(string $directory = ''): array
            {
                return [];
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
                return $path;
            }
        };
        $this->app->make(StorageManager::class)->extend('spaces', $disk);

        $response = $this->upload($this->csrf(), 'secret-key', $this->png());

        $this->assertSame(502, $response->getStatusCode());
        $this->assertStringContainsString('AccessDenied', (string) $response->getBody());
    }

    public function testUploadRefusesNonImages(): void
    {
        $this->app->make(StorageManager::class)->extend('spaces', $this->memoryDisk());
        $this->assertSame(422, $this->upload($this->csrf(), 'secret-key', '<?php echo 1;')->getStatusCode());
        $this->assertSame([], $this->app->make(CarouselStore::class)->all());
    }

    public function testUploadsAnswer503WithoutAKeyButTheButtonStillShows(): void
    {
        $this->app->make(\Naluz\Config\Repository::class)->set('site.upload_key', '');
        $this->assertSame(503, $this->upload($this->csrf(), '', $this->png())->getStatusCode());
        $this->assertStringContainsString('id="addBtn"', (string) $this->get('/')->getBody());
    }

    private function fakeYouTube(int $status, array $body = []): void
    {
        $client = new \GuzzleHttp\Client([
            'handler' => \GuzzleHttp\HandlerStack::create(new \GuzzleHttp\Handler\MockHandler([new \GuzzleHttp\Psr7\Response($status, [], json_encode($body))])),
            'http_errors' => false,
        ]);
        $this->app->instance(YouTube::class, new YouTube($client));
    }

    private function changeSong(string $token, string $key, string $url): \Psr\Http\Message\ResponseInterface
    {
        $request = (new ServerRequest('POST', '/song', ['X-CSRF-TOKEN' => $token, 'Accept' => 'application/json']))
            ->withParsedBody(['key' => $key, 'url' => $url]);
        return $this->send($request);
    }

    public function testHomePageShowsTheDefaultSong(): void
    {
        $html = (string) $this->get('/')->getBody();
        $this->assertStringContainsString('data-yt="fu9yk7gCTbc"', $html);
        $this->assertStringContainsString('Palagi', $html);
        $this->assertStringContainsString('id="songBtn"', $html);
        $this->assertStringContainsString('id="songForm"', $html);
        $this->assertStringContainsString('Change the song', $html);
        $this->assertStringContainsString('https://www.youtube.com/watch?v=fu9yk7gCTbc', $html);
    }

    public function testChangingTheSongMakesItTheNewDefault(): void
    {
        $this->fakeYouTube(200, ['title' => 'Bakit Ba Ikaw', 'author_name' => 'Michael Pangilinan']);

        $response = $this->changeSong($this->csrf(), 'secret-key', 'https://youtu.be/dQw4w9WgXcQ');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['id' => 'dQw4w9WgXcQ', 'title' => 'Bakit Ba Ikaw', 'artist' => 'Michael Pangilinan'], $this->decode($response));

        $html = (string) $this->get('/')->getBody();
        $this->assertStringContainsString('data-yt="dQw4w9WgXcQ"', $html);
        $this->assertStringContainsString('Bakit Ba Ikaw', $html);
        $this->assertStringContainsString('Michael Pangilinan', $html);
    }

    public function testOnlyYouTubeLinksAreAccepted(): void
    {
        $response = $this->changeSong($this->csrf(), 'secret-key', 'https://example.com/song.mp3');

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Paste a YouTube link', (string) $response->getBody());
        $this->assertFalse($this->app->make(SongStore::class)->hasSaved());
    }

    public function testAVideoYouTubeDoesNotKnowIsRejected(): void
    {
        $this->fakeYouTube(404);
        $response = $this->changeSong($this->csrf(), 'secret-key', 'https://youtu.be/aaaaaaaaaaa');

        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($this->app->make(SongStore::class)->hasSaved());
    }

    public function testChangingTheSongNeedsTheUploadKey(): void
    {
        $response = $this->changeSong($this->csrf(), 'wrong', 'https://youtu.be/dQw4w9WgXcQ');

        $this->assertSame(403, $response->getStatusCode());
        $this->assertFalse($this->app->make(SongStore::class)->hasSaved());
    }

    private function changeMessage(string $token, string $key, string $message): \Psr\Http\Message\ResponseInterface
    {
        $request = (new ServerRequest('POST', '/letter', ['X-CSRF-TOKEN' => $token, 'Accept' => 'application/json']))
            ->withParsedBody(['key' => $key, 'message' => $message]);
        return $this->send($request);
    }

    public function testHomePageShowsTheMessageAndItsForm(): void
    {
        $html = (string) $this->get('/')->getBody();

        $this->assertStringContainsString('<p>Old first.</p>', $html);
        $this->assertStringContainsString('id="letterBtn"', $html);
        $this->assertStringContainsString('id="letterForm"', $html);
        $this->assertStringContainsString("Old first.\n\nOld second.", $html);
    }

    public function testReplacingTheMessageArchivesTheOldOneFirst(): void
    {
        $disk = $this->memoryDisk();
        $this->app->make(StorageManager::class)->extend('spaces', $disk);
        $this->app->make(\Naluz\Config\Repository::class)->set('filesystems.disks.spaces.folder', 'yam');

        $response = $this->changeMessage($this->csrf(), 'secret-key', "Brand new.\n\nSecond new.");
        $body = $this->decode($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($body['changed']);
        $this->assertSame(['Brand new.', 'Second new.'], $body['paragraphs']);
        $this->assertSame('spaces', $body['archivedTo']);

        // the old message was uploaded as a file...
        $this->assertCount(1, $disk->files);
        $path = array_key_first($disk->files);
        $this->assertMatchesRegularExpression('#^yam/messages/\d{8}-\d{6}-[0-9a-f]{8}\.txt$#', $path);
        $this->assertStringContainsString("Old first.\n\nOld second.", $disk->files[$path]);

        // ...and the new one is what the page shows now
        $html = (string) $this->get('/')->getBody();
        $this->assertStringContainsString('<p>Brand new.</p>', $html);
        $this->assertStringNotContainsString('Old first.', $html);
        $this->assertSame($path, $this->app->make(LetterStore::class)->archive()[0]['key']);
    }

    public function testTheOldMessageStaysWhenItCannotBeArchived(): void
    {
        $this->app->make(StorageManager::class)->extend('spaces', new class implements Filesystem {
            public function exists(string $path): bool
            {
                return false;
            }
            public function get(string $path): string
            {
                return '';
            }
            public function put(string $path, mixed $contents): void
            {
                throw new \Naluz\Storage\StorageException('Storage rejected the upload (HTTP 403, AccessDenied).');
            }
            public function delete(string $path): bool
            {
                return true;
            }
            public function copy(string $from, string $to): void
            {
            }
            public function move(string $from, string $to): void
            {
            }
            public function size(string $path): int
            {
                return 0;
            }
            public function lastModified(string $path): int
            {
                return 0;
            }
            public function mimeType(string $path): string
            {
                return '';
            }
            public function files(string $directory = ''): array
            {
                return [];
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
                return $path;
            }
        });

        $response = $this->changeMessage($this->csrf(), 'secret-key', 'Should not appear.');

        $this->assertSame(502, $response->getStatusCode());
        $this->assertStringContainsString('The message was not changed', (string) $response->getBody());
        $this->assertSame(['Old first.', 'Old second.'], $this->app->make(LetterStore::class)->paragraphs());
    }

    public function testTheMessageNeedsTheUploadKeyAndSomeText(): void
    {
        $this->assertSame(403, $this->changeMessage($this->csrf(), 'wrong', 'New text')->getStatusCode());
        $this->assertSame(422, $this->changeMessage($this->csrf(), 'secret-key', "  \n ")->getStatusCode());
        $this->assertSame(['Old first.', 'Old second.'], $this->app->make(LetterStore::class)->paragraphs());
    }

    public function testSavingTheSameMessageChangesNothing(): void
    {
        $disk = $this->memoryDisk();
        $this->app->make(StorageManager::class)->extend('spaces', $disk);

        $response = $this->changeMessage($this->csrf(), 'secret-key', "Old first.\n\nOld second.");

        $this->assertFalse($this->decode($response)['changed']);
        $this->assertSame([], $disk->files);
    }
}

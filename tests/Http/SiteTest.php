<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Services\CarouselStore;
use Naluz\Storage\Filesystem;
use Naluz\Storage\StorageManager;
use Naluz\Tests\TestCase;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use Nyholm\Psr7\UploadedFile;

final class SiteTest extends TestCase
{
    private string $manifest;

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
    }

    protected function tearDown(): void
    {
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
        $request = (new ServerRequest('POST', '/photos', ['X-CSRF-TOKEN' => $token, 'Accept' => 'application/json']))
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
        $this->assertSame(403, $this->upload($this->csrf(), 'nope', $this->png())->getStatusCode());
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
}

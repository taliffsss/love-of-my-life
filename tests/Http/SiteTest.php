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
        // never write backups into the real storage/app/letters: every test gets an in-memory disk unless it brings its own
        $this->app->make(StorageManager::class)->extend('spaces', $this->memoryDisk());
        $this->letterFile = sys_get_temp_dir() . '/letter-' . bin2hex(random_bytes(4)) . '.json';
        $this->app->instance(LetterStore::class, new LetterStore($this->letterFile, ['Old first.', 'Old second.'], 'Jinelin'));
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

    public function testEveryDialogCanBeClosedWithoutPassingValidation(): void
    {
        $html = (string) $this->get('/')->getBody();

        // Close is a submit button inside a <form method="dialog">; without formnovalidate the browser would run the
        // "required" check first and refuse to close the message dialog while its box is empty.
        $this->assertSame(3, substr_count($html, 'value="close" formnovalidate'));
        $this->assertSame(3, substr_count($html, 'value="close"'));
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

    private function changeMessage(string $token, string $key, string $message, string $name = 'Jinelin'): \Psr\Http\Message\ResponseInterface
    {
        $request = (new ServerRequest('POST', '/letter', ['X-CSRF-TOKEN' => $token, 'Accept' => 'application/json']))
            ->withParsedBody(['key' => $key, 'name' => $name, 'message' => $message]);
        return $this->send($request);
    }

    public function testHomePageShowsTheMessageAndItsForm(): void
    {
        $html = (string) $this->get('/')->getBody();

        $this->assertStringContainsString('<p>Old first.</p>', $html);
        $this->assertStringContainsString('id="letterBtn"', $html);
        $this->assertStringContainsString('id="letterForm"', $html);
        // a new message starts from an empty box and is required
        $this->assertMatchesRegularExpression('#<textarea id="letterMsg"[^>]*\brequired\b[^>]*></textarea>#', $html);
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
        $empty = $this->changeMessage($this->csrf(), 'secret-key', "  \n ");
        $this->assertSame(422, $empty->getStatusCode());
        $this->assertStringContainsString('The message is required.', (string) $empty->getBody());
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

    private function head(): string
    {
        $html = (string) $this->get('/')->getBody();
        return substr($html, 0, (int) strpos($html, '</head>'));
    }

    public function testHeadCarriesTheSearchAndSharingTags(): void
    {
        $head = $this->head();

        foreach (
            [
            '<title>For Jinelin Basela | A Romantic Photo Slideshow</title>',
            '<link rel="canonical" href="https://love.test/" />',
            '<meta name="robots" content="index, follow, max-image-preview:large',
            '<link rel="alternate" hreflang="x-default" href="https://love.test/" />',
            '<meta property="og:image" content="https://love.test/og-image.png" />',
            '<meta property="og:image:type" content="image/png" />',
            '<meta name="twitter:card" content="summary_large_image" />',
            '<meta name="twitter:image:alt"',
            '<link rel="manifest" href="/manifest.webmanifest" />',
            '<link rel="apple-touch-icon" href="/apple-touch-icon.png" />',
            '<meta name="color-scheme" content="dark" />',
            ] as $tag
        ) {
            $this->assertStringContainsString($tag, $head);
        }
        $description = preg_match('/<meta name="description" content="([^"]+)"/', $head, $m) === 1 ? html_entity_decode($m[1]) : '';
        $this->assertGreaterThanOrEqual(70, mb_strlen($description));
        $this->assertLessThanOrEqual(160, mb_strlen($description));
    }

    public function testTheFirstPhotoIsPreloadedAndHasUsefulAltText(): void
    {
        $this->app->make(CarouselStore::class)->add('yam/a.jpg', 'https://cdn.test/yam/a.jpg');
        $html = (string) $this->get('/')->getBody();

        $first = $this->app->make(\App\Services\PhotoCatalog::class)->all()[0];
        $this->assertStringContainsString('<link rel="preload" as="image" href="' . $first . '" fetchpriority="high" />', $html);
        $this->assertMatchesRegularExpression('#<img class="fg"[^>]*alt="Photo memory 1 of \d+ for Jinelin Basela"[^>]*fetchpriority="high"#', $html);
    }

    public function testStructuredDataIsValidJsonLd(): void
    {
        $this->app->make(CarouselStore::class)->add('yam/a.jpg', 'https://cdn.test/yam/a.jpg');
        $html = (string) $this->get('/')->getBody();

        $this->assertSame(1, preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m));
        $data = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('https://schema.org', $data['@context']);
        $byType = array_column($data['@graph'], null, '@type');
        $this->assertEqualsCanonicalizing(['WebSite', 'WebPage', 'Person', 'ImageGallery'], array_keys($byType));
        $this->assertSame('https://love.test/', $byType['WebPage']['url']);
        $this->assertSame('Jinelin Basela', $byType['Person']['name']);
        $this->assertSame('https://love.test/og-image.png', $byType['WebPage']['primaryImageOfPage']['url']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $byType['WebPage']['dateModified']);
        $urls = array_column($byType['ImageGallery']['associatedMedia'], 'contentUrl');
        $this->assertContains('https://cdn.test/yam/a.jpg', $urls);
        foreach ($urls as $url) {
            $this->assertStringStartsWith('http', $url, 'gallery images must be absolute URLs');
        }
    }

    public function testSitemapIsValidXmlAndListsEveryPhotoAsAnImage(): void
    {
        $this->app->make(CarouselStore::class)->add('yam/a.jpg', 'https://cdn.test/yam/a.jpg');
        $response = $this->get('/sitemap.xml');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringStartsWith('application/xml', $response->getHeaderLine('Content-Type'));

        $xml = new \DOMDocument();
        $this->assertTrue($xml->loadXML((string) $response->getBody()), 'sitemap.xml is not well-formed XML');
        $xpath = new \DOMXPath($xml);
        $xpath->registerNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xpath->registerNamespace('i', 'http://www.google.com/schemas/sitemap-image/1.1');

        $this->assertSame('https://love.test/', $xpath->evaluate('string(//s:url/s:loc)'));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $xpath->evaluate('string(//s:url/s:lastmod)'));
        $images = [];
        foreach ($xpath->query('//i:image/i:loc') as $node) {
            $images[] = $node->textContent;
        }
        $this->assertContains('https://cdn.test/yam/a.jpg', $images);
        $this->assertContains('https://love.test/photos/01.jpg', $images, 'local photos become absolute URLs');
    }

    public function testRobotsTxtPointsToTheSitemapAndHidesTheActions(): void
    {
        $response = $this->get('/robots.txt');
        $body = (string) $response->getBody();

        $this->assertStringStartsWith('text/plain', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString("User-agent: *\nAllow: /\n", $body);
        foreach (['/api/', '/upload', '/song', '/letter'] as $path) {
            $this->assertStringContainsString('Disallow: ' . $path . "\n", $body);
        }
        $this->assertStringContainsString("Sitemap: https://love.test/sitemap.xml\n", $body);
    }

    public function testWebManifestAndIconsExist(): void
    {
        $response = $this->get('/manifest.webmanifest');
        $manifest = $this->decode($response);

        $this->assertStringStartsWith('application/manifest+json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('For Jinelin Basela', $manifest['name']);
        $this->assertSame('#160a14', $manifest['background_color']);
        foreach ($manifest['icons'] as $icon) {
            $file = dirname(__DIR__, 2) . '/public' . $icon['src'];
            $this->assertFileExists($file);
            [$w, $h] = getimagesize($file);
            $this->assertSame($icon['sizes'], $w . 'x' . $h);
        }
        $this->assertFileExists(dirname(__DIR__, 2) . '/public/apple-touch-icon.png');
    }

    public function testJsonEndpointsAreKeptOutOfSearchResults(): void
    {
        $this->assertSame('noindex, nofollow', $this->get('/api/ping')->getHeaderLine('X-Robots-Tag'));
        $this->assertSame('noindex, nofollow', $this->get('/api/photos')->getHeaderLine('X-Robots-Tag'));
        // an action that completes normally (saving the unchanged message)
        $same = $this->changeMessage($this->csrf(), 'secret-key', "Old first.\n\nOld second.");
        $this->assertSame('noindex, nofollow', $same->getHeaderLine('X-Robots-Tag'));
        $this->assertSame('', $this->get('/')->getHeaderLine('X-Robots-Tag'), 'the home page itself stays indexable');
    }

    /** @return array<string,mixed> */
    private function siteConfig(?string $uploadKeyEnv): array
    {
        $previous = getenv('UPLOAD_KEY');
        $uploadKeyEnv === null ? putenv('UPLOAD_KEY') : putenv('UPLOAD_KEY=' . $uploadKeyEnv);
        \Naluz\Support\Env::flush();
        try {
            return require dirname(__DIR__, 2) . '/config/site.php';
        } finally {
            $previous === false ? putenv('UPLOAD_KEY') : putenv('UPLOAD_KEY=' . $previous);
        }
    }

    public function testTheUploadKeyDefaultsToJinelinBasela(): void
    {
        $this->assertSame('jinelin basela', $this->siteConfig(null)['upload_key'], 'UPLOAD_KEY not set');
        $this->assertSame('jinelin basela', $this->siteConfig('')['upload_key'], 'UPLOAD_KEY= (empty) in .env');
        $this->assertSame('another key', $this->siteConfig('another key')['upload_key'], 'a key set in .env wins');
    }

    public function testTheDefaultKeyAcceptsTheNameHoweverItIsTyped(): void
    {
        $this->app->make(\Naluz\Config\Repository::class)->set('site.upload_key', 'jinelin basela');

        foreach (['jinelin basela', 'Jinelin Basela', '  JINELIN   BASELA ', "Jinelin Basela\r"] as $typed) {
            $response = $this->changeMessage($this->csrf(), $typed, "Old first.\n\nOld second.");
            $this->assertSame(200, $response->getStatusCode(), 'rejected: ' . json_encode($typed));
        }
        $this->assertSame(403, $this->changeMessage($this->csrf(), 'jinelin', 'x')->getStatusCode());
    }

    public function testQuotesAndLineEndingsInTheConfiguredKeyAreIgnored(): void
    {
        // Docker's env_file keeps quotes literally, and a Windows-edited file leaves a carriage return
        foreach (['"jinelin basela"', "'Jinelin Basela'", "jinelin basela\r", '"Jinelin  Basela"' . "\r"] as $configured) {
            $this->app->make(\Naluz\Config\Repository::class)->set('site.upload_key', $configured);
            $response = $this->changeMessage($this->csrf(), 'jinelin basela', "Old first.\n\nOld second.");
            $this->assertSame(200, $response->getStatusCode(), 'configured as ' . json_encode($configured));
        }
    }

    public function testTheSongFormUsesTheSameKeyRules(): void
    {
        $this->app->make(\Naluz\Config\Repository::class)->set('site.upload_key', '"jinelin basela"');
        $this->fakeYouTube(200, ['title' => 'T', 'author_name' => 'A']);

        $this->assertSame(200, $this->changeSong($this->csrf(), 'Jinelin Basela', 'https://youtu.be/dQw4w9WgXcQ')->getStatusCode());
    }

    public function testSavedChangesReloadThePageWithoutAskingForThePasswordAgain(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/app.js');

        // all three actions end with the same reload, which leaves a short-lived, one-shot hint for the next page load
        $this->assertSame(3, substr_count($js, 'reloadAfterSuccess()'), 'photos, song and message each reload on success');
        $this->assertStringContainsString('sessionStorage.setItem(SKIP_GATE', $js);
        $this->assertStringContainsString('sessionStorage.removeItem(SKIP_GATE);', $js, 'the hint is removed as soon as it is read');
        $this->assertStringContainsString('SKIP_GATE_MS = 3e4', $js, 'the hint expires after 30 seconds');
        $this->assertStringContainsString('if (consumeSkipGate())', $js);
    }

    /** @return list<string> the src of every foreground slide image, in carousel order */
    private function slideSources(string $html): array
    {
        preg_match_all('#<img class="fg" src="([^"]+)"#', $html, $m);
        return array_map('html_entity_decode', $m[1]);
    }

    public function testNewPhotosAreTheFirstSlidesNewestFirst(): void
    {
        $store = $this->app->make(CarouselStore::class);
        $store->add('yam/old.jpg', 'https://cdn.test/yam/old.jpg');
        $store->add('yam/new.jpg', 'https://cdn.test/yam/new.jpg');

        $html = (string) $this->get('/')->getBody();
        $slides = $this->slideSources($html);

        $this->assertSame('https://cdn.test/yam/new.jpg', $slides[0], 'the newest upload is the very first slide');
        $this->assertSame('https://cdn.test/yam/old.jpg', $slides[1]);
        $this->assertSame('/photos/01.jpg', $slides[2], 'the photos folder follows the uploads');
        $this->assertStringContainsString('<link rel="preload" as="image" href="https://cdn.test/yam/new.jpg"', $html);
        $this->assertMatchesRegularExpression('#<img class="fg" src="https://cdn.test/yam/new.jpg"[^>]*alt="Photo memory 1 of #', $html);
    }

    public function testASecondUploadMovesToTheFrontAndTheSitemapAndApiFollow(): void
    {
        $this->app->make(StorageManager::class)->extend('spaces', $this->memoryDisk());
        $this->upload($this->csrf(), 'secret-key', $this->png());
        $first = $this->app->make(CarouselStore::class)->urls()[0];
        $this->upload($this->csrf(), 'secret-key', $this->png());
        $urls = $this->app->make(CarouselStore::class)->urls();
        $this->assertCount(2, $urls);

        $slides = $this->slideSources((string) $this->get('/')->getBody());
        $this->assertSame([$urls[1], $urls[0]], array_slice($slides, 0, 2), 'the second upload is now first');
        $this->assertSame($first, $slides[1]);

        $api = $this->decode($this->get('/api/photos'));
        $this->assertSame([$urls[1], $urls[0]], array_column($api['photos'], 'url'), 'the API lists them in carousel order');

        preg_match_all('#<image:loc>([^<]+)</image:loc>#', (string) $this->get('/sitemap.xml')->getBody(), $m);
        $this->assertSame([$urls[1], $urls[0]], array_slice($m[1], 0, 2), 'the sitemap lists them in carousel order');
    }

    public function testThePageUploadsABatchLastToFirstSoItKeepsThePickedOrder(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/app.js');
        $this->assertStringContainsString('[...files].reverse().entries()', $js);
    }

    public function testTheMessageFormHasANameFieldFilledWithTheCurrentName(): void
    {
        $html = (string) $this->get('/')->getBody();

        $this->assertMatchesRegularExpression('#<input id="letterName"[^>]*\brequired\b[^>]*value="Jinelin"#', $html);
        $this->assertStringContainsString('<h2 id="letterFor">Jinelin</h2>', $html);
        $this->assertStringContainsString('aria-label="A letter for Jinelin"', $html);
        $this->assertMatchesRegularExpression('#<textarea id="letterMsg"[^>]*\brequired\b[^>]*></textarea>#', $html, 'the message box still starts empty');
    }

    public function testChangingTheNameChangesWhoTheLetterIsFor(): void
    {
        $response = $this->changeMessage($this->csrf(), 'secret-key', 'A new message.', '  Maria   Clara ');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Maria Clara', $this->decode($response)['name']);

        $html = (string) $this->get('/')->getBody();
        $this->assertStringContainsString('<p class="eyebrow">A letter for</p>', $html);
        $this->assertStringContainsString('<h2 id="letterFor">Maria Clara</h2>', $html);
        $this->assertStringContainsString('aria-label="A letter for Maria Clara"', $html);
        $this->assertMatchesRegularExpression('#<input id="letterName"[^>]*value="Maria Clara"#', $html, 'the form is filled with the new name');
        $this->assertStringNotContainsString('<h2 id="letterFor">Jinelin</h2>', $html);
    }

    public function testTheNameIsRequiredAndNothingChangesWithoutIt(): void
    {
        foreach (['', '   '] as $blank) {
            $response = $this->changeMessage($this->csrf(), 'secret-key', 'Some text.', $blank);
            $this->assertSame(422, $response->getStatusCode());
            $this->assertStringContainsString('The name is required.', (string) $response->getBody());
        }
        $tooLong = $this->changeMessage($this->csrf(), 'secret-key', 'Some text.', str_repeat('a', 61));
        $this->assertSame(422, $tooLong->getStatusCode());

        $letters = $this->app->make(LetterStore::class);
        $this->assertSame('Jinelin', $letters->name());
        $this->assertSame(['Old first.', 'Old second.'], $letters->paragraphs());
        $this->assertSame([], $letters->archive(), 'a rejected save archives nothing');
    }

    public function testTheBackupFileSaysWhoTheOldLetterWasFor(): void
    {
        $disk = $this->memoryDisk();
        $this->app->make(StorageManager::class)->extend('spaces', $disk);

        $this->changeMessage($this->csrf(), 'secret-key', 'Brand new.', 'Maria');

        $backup = $disk->files[array_key_first($disk->files)];
        $this->assertStringContainsString("A letter for: Jinelin\n", $backup, 'the OLD name, not the new one');
        $this->assertStringContainsString("Old first.\n\nOld second.", $backup);
        $this->assertStringNotContainsString('Maria', $backup);
    }

    public function testOnlyChangingTheNameStillBacksUpTheOldLetter(): void
    {
        $disk = $this->memoryDisk();
        $this->app->make(StorageManager::class)->extend('spaces', $disk);

        $response = $this->changeMessage($this->csrf(), 'secret-key', "Old first.\n\nOld second.", 'Maria');

        $this->assertTrue($this->decode($response)['changed'], 'a new name alone is a change');
        $this->assertCount(1, $disk->files);
        $this->assertSame('Maria', $this->app->make(LetterStore::class)->name());
    }

    public function testTheSameNameAndMessageChangeNothing(): void
    {
        $disk = $this->memoryDisk();
        $this->app->make(StorageManager::class)->extend('spaces', $disk);

        $response = $this->changeMessage($this->csrf(), 'secret-key', "Old first.\n\nOld second.", ' Jinelin ');

        $this->assertFalse($this->decode($response)['changed']);
        $this->assertSame([], $disk->files);
    }

    public function testPingIsNeverRateLimitedButTheOtherApiRoutesAre(): void
    {
        foreach (range(1, 80) as $i) {
            $this->assertSame(200, $this->get('/api/ping')->getStatusCode(), "ping $i");
        }

        $statuses = [];
        foreach (range(1, 62) as $i) {
            $statuses[] = $this->get('/api/photos')->getStatusCode();
        }
        $this->assertSame(array_fill(0, 60, 200), array_slice($statuses, 0, 60));
        $this->assertSame([429, 429], array_slice($statuses, 60));
        $this->assertSame(200, $this->get('/api/ping')->getStatusCode(), 'even while another route is being limited');
    }

    public function testTheThrottleAliasIsTheResettingLimiter(): void
    {
        $aliases = (array) config('app.middleware_aliases');
        $this->assertSame(\App\Http\Middleware\RateLimit::class, $aliases['throttle']);
    }

    public function testTheCarouselWaitsBehindTheBlurAndStartsWithTheFirstPhoto(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/app.js');

        // nothing may advance while the password screen is up: the timer only begins in startSlideshow()
        $this->assertStringContainsString('if (!running || slides.length < 2) return;', $js);
        $this->assertStringContainsString('running = true;', $js);
        // ...which is called when the blur starts to clear, and waits for the blur before the first advance
        $this->assertStringContainsString('BLUR_CLEAR_MS = 2800', $js);
        $this->assertStringContainsString('restart(BLUR_CLEAR_MS);', $js);
        $this->assertMatchesRegularExpression('/show\.classList\.remove\("locked"\);\s*startSlideshow\(\);/', $js, 'unlocking starts the slideshow');
        // the CSS blur transition is what BLUR_CLEAR_MS has to cover
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/app.css');
        $this->assertMatchesRegularExpression('/\.show\s*\{[^}]*transition:[^;]*filter 2\.6s[^;]*transform 2\.8s/s', $css);
    }
}

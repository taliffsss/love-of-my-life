<?php

declare(strict_types=1);

namespace Naluz\Tests\Unit;

use App\Services\YouTube;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class YouTubeTest extends TestCase
{
    /** @return array<string,array{string,?string}> */
    public static function links(): array
    {
        return [
            'watch' => ['https://www.youtube.com/watch?v=fu9yk7gCTbc', 'fu9yk7gCTbc'],
            'watch with extras' => ['https://www.youtube.com/watch?v=fu9yk7gCTbc&list=PL1&t=30s', 'fu9yk7gCTbc'],
            'short link' => ['https://youtu.be/fu9yk7gCTbc?si=abc', 'fu9yk7gCTbc'],
            'no scheme' => ['youtube.com/watch?v=fu9yk7gCTbc', 'fu9yk7gCTbc'],
            'mobile' => ['https://m.youtube.com/watch?v=fu9yk7gCTbc', 'fu9yk7gCTbc'],
            'music' => ['https://music.youtube.com/watch?v=fu9yk7gCTbc', 'fu9yk7gCTbc'],
            'embed' => ['https://www.youtube.com/embed/fu9yk7gCTbc', 'fu9yk7gCTbc'],
            'shorts' => ['https://www.youtube.com/shorts/fu9yk7gCTbc', 'fu9yk7gCTbc'],
            'padded' => ["  https://youtu.be/fu9yk7gCTbc \n", 'fu9yk7gCTbc'],
            'other site' => ['https://vimeo.com/watch?v=fu9yk7gCTbc', null],
            'look-alike host' => ['https://youtube.com.evil.test/watch?v=fu9yk7gCTbc', null],
            'bad id' => ['https://www.youtube.com/watch?v=short', null],
            'no id' => ['https://www.youtube.com/', null],
            'plain text' => ['my favourite song', null],
            'empty' => ['', null],
        ];
    }

    #[DataProvider('links')]
    public function testParseId(string $url, ?string $expected): void
    {
        $this->assertSame($expected, YouTube::parseId($url));
    }

    private function youtube(Response|\Throwable $reply): YouTube
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([$reply])), 'http_errors' => false]);
        return new YouTube($client);
    }

    public function testLookupReadsTitleAndChannel(): void
    {
        $info = $this->youtube(new Response(200, [], json_encode(['title' => 'Palagi', 'author_name' => 'TJ Monterde'])))->lookup('fu9yk7gCTbc');
        $this->assertSame(['title' => 'Palagi', 'artist' => 'TJ Monterde'], $info);
    }

    public function testUnknownOrNonEmbeddableVideosAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->youtube(new Response(404))->lookup('aaaaaaaaaaa');
    }

    public function testWhenYouTubeIsUnreachableTheLinkIsStillAccepted(): void
    {
        $info = $this->youtube(new \GuzzleHttp\Exception\ConnectException('down', new \GuzzleHttp\Psr7\Request('GET', 'x')))->lookup('fu9yk7gCTbc');
        $this->assertSame('Our song', $info['title']);
    }
}

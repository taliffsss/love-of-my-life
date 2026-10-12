<?php

declare(strict_types=1);

namespace Naluz\Tests\Unit;

use App\Http\Middleware\RateLimit;
use Naluz\Cache\FileCache;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RateLimitTest extends TestCase
{
    private string $dir;
    private FileCache $cache;
    private int $now = 1_800_000_000;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/ratelimit-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        $this->cache = new FileCache($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    private function limiter(string $max = '3', string $minutes = '1'): RateLimit
    {
        return new RateLimit($this->cache, [$max, $minutes], fn (): int => $this->now);
    }

    private function hit(RateLimit $limiter, string $path = '/api/photos', string $ip = '10.0.0.1'): ResponseInterface
    {
        $request = new ServerRequest('GET', $path, ['Accept' => 'application/json'], null, '1.1', ['REMOTE_ADDR' => $ip]);
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        };
        return $limiter->process($request, $handler);
    }

    private function counterFile(string $path, string $ip): string
    {
        return $this->dir . '/' . hash('sha256', 'throttle_' . sha1($ip . '|' . $path)) . '.cache';
    }

    public function testAllowsTheLimitAndThenAnswers429(): void
    {
        $limiter = $this->limiter('3');

        foreach ([2, 1, 0] as $remaining) {
            $response = $this->hit($limiter);
            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame((string) $remaining, $response->getHeaderLine('X-RateLimit-Remaining'));
        }
        $blocked = $this->hit($limiter);
        $this->assertSame(429, $blocked->getStatusCode());
        $this->assertSame('60', $blocked->getHeaderLine('Retry-After'));
    }

    public function testTheCounterStartsAgainWhenTheWindowHasEnded(): void
    {
        $limiter = $this->limiter('2');
        $this->hit($limiter);
        $this->hit($limiter);
        $this->assertSame(429, $this->hit($limiter)->getStatusCode());

        $this->now += 61;

        $this->assertSame(200, $this->hit($limiter)->getStatusCode(), 'a new minute is a new window');
        $this->assertSame(200, $this->hit($limiter)->getStatusCode());
        $this->assertSame(429, $this->hit($limiter)->getStatusCode());
    }

    public function testACounterLeftByAnEndedWindowDoesNotBlockAnything(): void
    {
        // The situation that took the site down: the counter file of a window that ended long ago still says 59 hits.
        $file = $this->counterFile('/api/photos', '10.0.0.1');
        file_put_contents($file, serialize([59, $this->now - 600]));

        $limiter = $this->limiter('60');
        foreach (range(1, 60) as $i) {
            $this->assertSame(200, $this->hit($limiter)->getStatusCode(), "request $i must pass: the old window is over");
        }
        $this->assertSame(429, $this->hit($limiter)->getStatusCode(), 'the limit still applies inside the new window');
    }

    public function testACounterThatKeepsGrowingCannotBlockForever(): void
    {
        $limiter = $this->limiter('60');
        // a health check every 30 seconds for two hours is far more than 60 requests, but never 60 in one minute
        for ($i = 0; $i < 240; $i++) {
            $this->assertSame(200, $this->hit($limiter, '/api/photos')->getStatusCode(), "probe $i");
            $this->now += 30;
        }
    }

    public function testThePingEndpointIsNeverLimited(): void
    {
        $limiter = $this->limiter('1');
        foreach (range(1, 200) as $i) {
            $this->assertSame(200, $this->hit($limiter, '/api/ping')->getStatusCode(), "ping $i");
        }
    }

    public function testEveryClientAndPathHasItsOwnCounter(): void
    {
        $limiter = $this->limiter('1');

        $this->assertSame(200, $this->hit($limiter, '/api/photos', '10.0.0.1')->getStatusCode());
        $this->assertSame(429, $this->hit($limiter, '/api/photos', '10.0.0.1')->getStatusCode());
        $this->assertSame(200, $this->hit($limiter, '/api/photos', '10.0.0.2')->getStatusCode(), 'another client');
        $this->assertSame(200, $this->hit($limiter, '/upload', '10.0.0.1')->getStatusCode(), 'another path');
    }

    public function testTheWindowIsTakenFromTheSecondParameter(): void
    {
        $limiter = $this->limiter('1', '5');
        $this->hit($limiter);
        $this->assertSame('300', $this->hit($limiter)->getHeaderLine('Retry-After'));
    }
}

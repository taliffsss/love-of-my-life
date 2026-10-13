<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Naluz\Http\Request;
use Naluz\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Fixed-window rate limiter, used under the name `throttle` (`throttle:60,1` = 60 requests per minute per client IP and
 * path).
 *
 * It replaces the framework's Throttle because that one reads a counter back from the file cache without checking
 * whether its window has ended: the count then only ever grows and, once over the limit, answers 429 for good. With the
 * counters in a persistent volume that blocked /api/ping for the health checks and took the site down. Here an expired
 * window always starts again from zero, and /api/ping is never limited (it is the health endpoint).
 */
final class RateLimit implements MiddlewareInterface
{
    private const UNLIMITED = ['/api/ping'];

    /** @var \Closure():int */
    private readonly \Closure $clock;

    /**
     * @param list<string> $parameters [max requests, window in minutes]
     * @param (\Closure():int)|null $clock
     */
    public function __construct(private readonly CacheInterface $cache, private readonly array $parameters = [], ?\Closure $clock = null)
    {
        $this->clock = $clock ?? static fn (): int => time();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // The path exactly as the router resolves it (decoded, without leading/trailing slashes): "/unlock/",
        // "/unlock//" and "/%75nlock" reach the same route, so they must share one counter.
        $path = '/' . trim(rawurldecode($request->getUri()->getPath()), '/');
        if (in_array($path, self::UNLIMITED, true)) {
            return $handler->handle($request);
        }

        $max = max(1, (int) ($this->parameters[0] ?? 60));
        $window = max(1, (int) ($this->parameters[1] ?? 1)) * 60;
        $key = 'throttle_' . sha1(Request::ip($request) . '|' . $path);
        $now = ($this->clock)();

        // get() only returns an entry whose own lifetime has not ended; the stored window end is checked as well
        $entry = $this->cache->get($key);
        if (!is_array($entry) || !isset($entry[0], $entry[1]) || (int) $entry[1] <= $now) {
            $entry = [0, $now + $window];
        }
        $entry[0] = (int) $entry[0] + 1;
        $this->cache->set($key, $entry, max(1, (int) $entry[1] - $now));

        $hits = $entry[0];
        $headers = ['X-RateLimit-Limit' => (string) $max, 'X-RateLimit-Remaining' => (string) max(0, $max - $hits)];

        if ($hits > $max) {
            $headers['Retry-After'] = (string) max(1, (int) $entry[1] - $now);
            $body = ['message' => 'Too Many Requests'];
            return Request::expectsJson($request)
                ? Response::json($body, 429, $headers)
                : new Response(429, $headers + ['Content-Type' => 'text/plain'], 'Too Many Requests');
        }

        $response = $handler->handle($request);
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }
        return $response;
    }
}

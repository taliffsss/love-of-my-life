<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Traffic;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * On the public pages of the product (landing, demo, sign-in, sign-up, legal): records where the visit came from (App\Services\Traffic) and sets two
 * first-party cookies, a random visitor id and the first-touch campaign. Never on the pages people make for each other, never for bots, never breaks the page.
 */
final class TrackTraffic implements MiddlewareInterface
{
    public function __construct(private readonly Traffic $traffic)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        if ($request->getMethod() !== 'GET' || $response->getStatusCode() !== 200 || $this->traffic->isBot($request)) {
            return $response;
        }
        try {
            $cookies = $request->getCookieParams();
            $visitor = is_string($cookies[Traffic::VISITOR] ?? null) && preg_match('/^[a-f0-9]{32}$/', $cookies[Traffic::VISITOR]) === 1 ? $cookies[Traffic::VISITOR] : bin2hex(random_bytes(16));
            $tags = $this->traffic->tags($request);
            $this->traffic->record($visitor, $tags, $request->getUri()->getPath());
            $secure = $request->getUri()->getScheme() === 'https' ? '; Secure' : '';
            $set = static fn (string $name, string $value, int $days): string => sprintf('%s=%s; Path=/; Max-Age=%d; HttpOnly; SameSite=Lax%s', $name, $value, $days * 86400, $secure);
            $response = $response->withAddedHeader('Set-Cookie', $set(Traffic::VISITOR, $visitor, Traffic::VISITOR_DAYS));
            // first touch: the first tagged or referred visit wins for 30 days; a later tagged link does not replace it
            if ($this->traffic->attribution($request) === null && ($tags['tagged'] || $tags['medium'] === 'referral')) {
                $response = $response->withAddedHeader('Set-Cookie', $set(Traffic::ATTRIBUTION, $this->traffic->attributionCookieValue($tags), Traffic::ATTRIBUTION_DAYS));
            }
        } catch (\Throwable $e) {
            logger()->warning('[traffic] not recorded: {error}', ['error' => $e->getMessage()]);
        }
        return $response;
    }
}

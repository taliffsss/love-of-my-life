<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Sites;
use Naluz\Http\HttpException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Serves a page on its own host. A request to <name>.<saas domain> (or a page's custom domain) is routed to that page's
 * routes; nothing else of the product (dashboard, sign-in, landing page) is reachable there, except the /api/ping health
 * check. Requests to the product's own host pass through untouched.
 *
 * Runs before routing (app.middleware), so subdomains need no per-page configuration: claiming a name in the dashboard
 * is enough once the wildcard DNS record and certificate exist.
 */
final class ResolveSiteHost implements MiddlewareInterface
{
    public const ATTRIBUTE = 'site_host';

    public function __construct(private readonly Sites $sites)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $page = $this->sites->pageForHost($request->getUri()->getHost());
        if ($page === null) {
            return $handler->handle($request);
        }
        if ($page === false) {
            throw new HttpException(404, 'There is no page at this address.');
        }

        $path = '/' . trim($request->getUri()->getPath(), '/');
        if ($path === '/api/ping') {
            // the health check answers on every host (the release workflow checks the original site's domain)
            return $handler->handle($request);
        }
        $routes = [
            '/' => '/p/' . $page->slug,
            '/unlock' => '/p/' . $page->slug . '/unlock',
            '/robots.txt' => '/p/' . $page->slug . '/robots.txt',
            '/sitemap.xml' => '/p/' . $page->slug . '/sitemap.xml',
        ];
        if (!isset($routes[$path])) {
            throw new HttpException(404, 'Not Found');
        }

        return $handler->handle(
            $request->withUri($request->getUri()->withPath($routes[$path]))->withAttribute(self::ATTRIBUTE, $page->slug)
        );
    }
}

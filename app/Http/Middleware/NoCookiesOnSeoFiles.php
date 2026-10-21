<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * robots.txt, sitemap.xml and the manifest are public files for crawlers and caches: they carry no session cookie. (Every web route
 * starts a session, which sets one; this runs outside it and takes it off these three answers.)
 */
final class NoCookiesOnSeoFiles implements MiddlewareInterface
{
    private const PATHS = ['/robots.txt', '/sitemap.xml', '/manifest.webmanifest'];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        return in_array($request->getUri()->getPath(), self::PATHS, true) ? $response->withoutHeader('Set-Cookie') : $response;
    }
}

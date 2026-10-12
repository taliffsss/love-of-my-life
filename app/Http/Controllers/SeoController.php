<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Naluz\Http\Response;
use Psr\Http\Message\ResponseInterface;

final class SeoController
{
    public function sitemap(): ResponseInterface
    {
        $url = htmlspecialchars(rtrim((string) config('app.url'), '/') . '/', ENT_XML1);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>' . $url . '</loc></url></urlset>' . "\n";
        return Response::html($xml)->withHeader('Content-Type', 'application/xml; charset=utf-8');
    }

    public function robots(): ResponseInterface
    {
        $url = rtrim((string) config('app.url'), '/');
        return Response::html("User-agent: *\nAllow: /\nSitemap: {$url}/sitemap.xml\n")
            ->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}

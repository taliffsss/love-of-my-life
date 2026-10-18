<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Services\Designs;
use Naluz\Tests\TestCase;

final class LandingTest extends TestCase
{
    public function testTheLandingPageSellsThePlansAndLayouts(): void
    {
        $response = $this->get('/');
        $html = (string) $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('<meta name="robots" content="index, follow"', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://love.test/"', $html);
        foreach (['Sweet', 'Sweetheart', 'Forever', '₱59', '₱590', '₱99', '₱990', '₱1,499', 'a month', '2 months free', 'Most popular', 'Classic', 'Cinematic', 'Polaroid', 'yourname.love.test'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('Free preview</h3>', $html, 'the free draft is not sold as a plan');
        $this->assertStringContainsString('10 templates', $html);
        foreach (Designs::LAYOUTS as $key => $template) {
            $this->assertStringContainsString('<h3>' . $template['name'] . '</h3>', $html);
            $this->assertStringContainsString('href="/demo?layout=' . $key . '"', $html);
        }
        $this->assertStringContainsString('href="/register"', $html);
    }

    public function testTheDemoOpensWithAnyAnswerInAnyLayout(): void
    {
        $html = (string) $this->get('/demo?layout=cinematic&theme=midnight')->getBody();
        $this->assertStringContainsString('data-layout="cinematic"', $html);
        $this->assertStringContainsString('data-theme="midnight"', $html);
        $this->assertStringContainsString('"mode":"demo"', $html);
        $this->assertStringContainsString('"\/assets\/demo\/1.svg"', $html);
        $this->assertStringContainsString('Demo: type anything to open it.', $html);
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow"', $html);

        foreach (array_keys(Designs::LAYOUTS) as $key) {
            $demo = $this->get('/demo?layout=' . $key);
            $this->assertSame(200, $demo->getStatusCode(), $key);
            $this->assertStringContainsString('/assets/templates/' . $key . '.js', (string) $demo->getBody());
        }

        $fallback = (string) $this->get('/demo?layout=../../etc&theme=%22')->getBody();
        $this->assertStringContainsString('data-layout="classic"', $fallback);
        $this->assertStringContainsString('data-theme="rose"', $fallback);
        foreach ([1, 2, 3, 4] as $n) {
            $this->assertFileExists(base_path("public/assets/demo/{$n}.svg"));
        }
    }

    public function testRobotsKeepPeoplesPagesAndTheDashboardOutOfSearch(): void
    {
        $robots = (string) $this->get('/robots.txt')->getBody();
        $this->assertStringContainsString("Disallow: /p/\n", $robots);
        $this->assertStringContainsString("Disallow: /dashboard\n", $robots);
        $this->assertStringContainsString('Sitemap: https://love.test/sitemap.xml', $robots);

        $sitemap = (string) $this->get('/sitemap.xml')->getBody();
        $xml = simplexml_load_string($sitemap);
        $this->assertNotFalse($xml);
        $this->assertStringContainsString('<loc>https://love.test/</loc>', $sitemap);
        $this->assertStringNotContainsString('/p/', $sitemap);

        $manifest = $this->get('/manifest.webmanifest');
        $this->assertStringStartsWith('application/manifest+json', $manifest->getHeaderLine('Content-Type'));
    }

    public function testPingStaysOpenForHealthChecks(): void
    {
        $response = $this->get('/api/ping');
        $this->assertSame(['status' => 'ok', 'framework' => 'NaluzPHP'], $this->decode($response));
        $this->assertSame('noindex, nofollow', $response->getHeaderLine('X-Robots-Tag'));
        $this->assertSame('', $response->getHeaderLine('X-RateLimit-Limit'), 'never rate limited');
    }
}

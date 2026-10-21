<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Services\Designs;
use Naluz\Tests\TestCase;

final class LandingTest extends TestCase
{
    public function testTheLandingPageShowsASeasonOnlyTwoWeeksBeforeItsDay(): void
    {
        $this->seasons('2026-07-01');
        $html = (string) $this->get('/')->getBody();
        $this->assertStringContainsString('10 templates', $html);
        $this->assertStringNotContainsString('data-key="halloween"', $html);
        $this->assertStringNotContainsString('data-season="halloween"', $html, 'no Halloween chip in the hero');
        $this->assertStringNotContainsString('data-filter="season"', $html);

        $this->seasons('2026-10-20');
        $html = (string) $this->get('/')->getBody();
        $this->assertStringContainsString('11 templates', $html);
        $this->assertStringContainsString('data-key="halloween"', $html);
        $this->assertStringContainsString('data-season="halloween"', $html);
        $this->assertStringNotContainsString('data-key="christmas"', $html);
    }

    public function testTheLandingPageWearsTheLookOfTheSeasonThatIsOnAndAnyOtherOnlyAsAPreview(): void
    {
        $this->seasons('2026-07-01');
        $this->assertStringContainsString('<body class="lp lp-t-default"', (string) $this->get('/')->getBody());
        $this->seasons('2026-10-20');
        $this->assertStringContainsString('<body class="lp lp-t-halloween"', (string) $this->get('/')->getBody());
        $this->seasons('2026-11-02');
        $this->assertStringContainsString('<body class="lp lp-t-default"', (string) $this->get('/')->getBody(), 'after Halloween it goes back');

        // outside production, ?theme= previews a look, with its template and hero chip
        $html = (string) $this->get('/?theme=christmas')->getBody();
        $this->assertStringContainsString('<body class="lp lp-t-christmas"', $html);
        $this->assertStringContainsString('data-season="christmas"', $html);
        $this->assertStringContainsString('data-key="christmas"', $html);
        $this->assertStringNotContainsString('data-key="halloween"', $html);
        $this->assertStringContainsString('lp-t-default', (string) $this->get('/?theme=nonsense')->getBody(), 'only the four looks');
    }

    public function testTheLandingPageSellsThePlansAndLayouts(): void
    {
        $response = $this->get('/');
        $html = (string) $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('<meta name="robots" content="index, follow"', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://love.test/"', $html);
        foreach (['Sweet', 'Sweetheart', 'Forever', '₱200', '₱2,400', '₱350', '₱4,200', '₱4,999', 'a month', 'Most popular', 'Classic', 'Cinematic', 'Polaroid', 'yourname.love.test'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('months free', $html, 'no yearly discount any more');
        $this->assertStringContainsString('Need more photos? Add 12 for ₱100', $html);
        $this->assertStringContainsString('Sweet 12, Sweetheart 40 and Forever 60', $html, 'what each plan includes, shared by the account');
        $this->assertStringContainsString('up to 10 MB', $html);
        $this->assertStringNotContainsString('Free preview</h3>', $html, 'the free draft is not sold as a plan');
        $this->assertStringContainsString('13 templates', $html);
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
        $this->app->make(\Naluz\Config\Repository::class)->set('app.env', 'production'); // other environments block everything
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

    public function testTheSitemapListsExactlyThePagesThatAskToBeIndexed(): void
    {
        $xml = simplexml_load_string((string) $this->get('/sitemap.xml')->getBody());
        $listed = array_map('strval', (array) $xml->xpath('//*[local-name()="loc"]'));
        $this->assertSame(['https://love.test/', 'https://love.test/terms', 'https://love.test/privacy'], $listed);

        // every listed page really is index, follow; and none of the noindex ones is listed
        foreach ($listed as $url) {
            $this->assertStringContainsString('<meta name="robots" content="index, follow"', (string) $this->get(parse_url($url, PHP_URL_PATH) ?: '/')->getBody(), $url);
        }
        foreach (['/demo', '/register', '/login'] as $path) {
            $this->assertStringContainsString('noindex', (string) $this->get($path)->getBody(), $path);
            $this->assertNotContains('https://love.test' . $path, $listed);
        }
        $this->assertStringContainsString('<lastmod>' . config('saas.legal.updated') . '</lastmod>', (string) $this->get('/sitemap.xml')->getBody(), 'the legal documents carry their date');
        $this->assertStringNotContainsString('<priority>', (string) $this->get('/sitemap.xml')->getBody(), 'no made-up priority or changefreq');
    }

    public function testRobotsKeepAccountAndAuthPagesOutAndStayOnePerLineWithTheSitemapLast(): void
    {
        $this->app->make(\Naluz\Config\Repository::class)->set('app.env', 'production');
        $robots = (string) $this->get('/robots.txt')->getBody();
        foreach (['/dashboard', '/account', '/admin', '/api/', '/email/', '/forgot-password', '/reset-password', '/auth/', '/p/'] as $path) {
            $this->assertStringContainsString("Disallow: {$path}\n", $robots);
        }
        $this->assertStringContainsString("Allow: /\n", $robots);
        $this->assertStringEndsWith("\nSitemap: https://love.test/sitemap.xml\n", $robots);
        $this->assertStringNotContainsString("Disallow: /demo", $robots, 'noindex pages stay crawlable so the noindex is read');
    }

    public function testANonProductionCopyBlocksEverything(): void
    {
        $this->app->make(\Naluz\Config\Repository::class)->set('app.env', 'staging');
        $this->assertSame("User-agent: *\nDisallow: /\n", (string) $this->get('/robots.txt')->getBody());
    }

    public function testTheSeoFilesCarryNoSessionCookie(): void
    {
        foreach (['/robots.txt', '/sitemap.xml', '/manifest.webmanifest'] as $path) {
            $this->assertSame('', $this->get($path)->getHeaderLine('Set-Cookie'), $path);
        }
        $this->assertNotSame('', $this->get('/')->getHeaderLine('Set-Cookie'), 'other pages still start a session');
    }

    public function testPingStaysOpenForHealthChecks(): void
    {
        $response = $this->get('/api/ping');
        $this->assertSame(['status' => 'ok', 'framework' => 'NaluzPHP'], $this->decode($response));
        $this->assertSame('noindex, nofollow', $response->getHeaderLine('X-Robots-Tag'));
        $this->assertSame('', $response->getHeaderLine('X-RateLimit-Limit'), 'never rate limited');
    }
}

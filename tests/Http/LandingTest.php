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

    public function testTheIndexablePagesHaveTheirOwnTitleAndExactlyOneDescription(): void
    {
        $titles = [];
        foreach (['/' => 'Digital love letter', '/invitations' => 'Online wedding, debut, christening invitations'] as $path => $start) {
            $html = (string) $this->get($path)->getBody();
            $this->assertSame(1, substr_count($html, '<meta name="description"'), "{$path}: one description tag, not the layout's default as well");
            $this->assertMatchesRegularExpression('#<title>' . preg_quote($start, '#') . '#', $html, $path);
            preg_match('#<title>(.*?)</title>#', $html, $m);
            $this->assertLessThanOrEqual(65, mb_strlen(html_entity_decode($m[1])), "{$path}: a title that fits the results page");
            preg_match('#<meta name="description" content="(.*?)"#', $html, $d);
            $this->assertLessThanOrEqual(160, mb_strlen(html_entity_decode($d[1])), "{$path}: a description that is not cut");
            $titles[$path] = $m[1];
            $descriptions[$path] = $d[1];
        }
        $this->assertNotSame($descriptions['/'], $descriptions['/invitations']);
        $this->assertStringContainsString('invitation', $descriptions['/invitations']);
    }

    public function testThePublicPagesAskGoogleFontsOnceAndDoNotBlockOnTheAnalyticsScript(): void
    {
        foreach (['/', '/invitations'] as $path) {
            $html = (string) $this->get($path)->getBody();
            $this->assertSame(1, substr_count($html, 'fonts.googleapis.com/css2'), "{$path}: one stylesheet request for every font");
            $this->assertStringContainsString('family=Newsreader:ital,wght@1,400..600&display=swap', $html, $path);
            $this->assertStringContainsString('family=Bricolage+Grotesque:wght@300..800&amp;family=Newsreader', $html, $path);
            $this->assertStringNotContainsString('{{--', $html, $path);
        }
        $this->assertSame(1, substr_count((string) $this->get('/login')->getBody(), 'fonts.googleapis.com/css2'), 'pages without Newsreader still ask once');
    }

    public function testTheFontOriginsAreHintedAndTheFirstScreensPictureIsPreloaded(): void
    {
        foreach (['/', '/invitations', '/birthday', '/login'] as $path) {
            $html = (string) $this->get($path)->getBody();
            foreach (['https://fonts.googleapis.com', 'https://fonts.gstatic.com'] as $origin) {
                $this->assertStringContainsString('rel="preconnect" href="' . $origin . '"', $html, "{$path} preconnect {$origin}");
                $this->assertStringContainsString('rel="dns-prefetch" href="' . $origin . '"', $html, "{$path} dns-prefetch {$origin}");
            }
            $this->assertSame(1, substr_count($html, 'rel="preconnect"') - 1, "{$path}: preconnect to fonts.gstatic.com is the crossorigin one");
        }
        // the largest thing on the invitation and occasion pages is Lettie's picture: it is preloaded, at high priority, from the same URL
        foreach (['/invitations' => '/assets/invitations/lettie-invite.png', '/birthday' => '/assets/occasions/birthday-lettie.svg', '/monthsary' => '/assets/occasions/partner-lettie.svg'] as $path => $image) {
            $html = (string) $this->get($path)->getBody();
            $this->assertStringContainsString('<link rel="preload" as="image" href="' . $image . '" fetchpriority="high" />', $html, $path);
            $this->assertStringContainsString('src="' . $image . '"', $html, $path);
            $this->assertLessThan(strpos($html, 'rel="stylesheet"'), strpos($html, 'rel="preload"'), "{$path}: preloaded before the stylesheets");
        }
        $this->assertStringNotContainsString('rel="preload"', (string) $this->get('/')->getBody(), 'the homepage has no picture to preload: its largest element is the headline');
    }

    public function testTheHeadingsCarryTheWordsPeopleSearchFor(): void
    {
        $home = (string) $this->get('/')->getBody();
        preg_match('#<h1[^>]*>(.*?)</h1>#s', $home, $h);
        $this->assertStringContainsString('digital love letter', strtolower(strip_tags($h[1])));
        $inv = (string) $this->get('/invitations')->getBody();
        preg_match('#<h1[^>]*>(.*?)</h1>#s', $inv, $h);
        $this->assertStringContainsString('invitation online', strtolower(strip_tags($h[1])));
        $this->assertStringNotContainsString('Three steps, one evening', $inv, 'its own H2, not the homepage\'s');
        $this->assertStringContainsString('three steps', strtolower($inv));
    }

    public function testTheTryItButtonsAreNamedByTheirVisibleText(): void
    {
        preg_match_all('#<a [^>]*data-demo[^>]*aria-label="([^"]*)"[^>]*>Try it</a>#', (string) $this->get('/')->getBody(), $m);
        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $label) {
            $this->assertStringStartsWith('Try it', $label, 'the accessible name contains the visible text');
        }
    }

    public function testTheBrandNameLosesStrayQuotesFromTheEnvironment(): void
    {
        $this->assertStringNotContainsString('&quot;Love of My Life', (string) $this->get('/')->getBody(), 'no quoted brand in titles or meta tags');
        $this->assertSame('Love of My Life', trim((string) '"Love of My Life"', " \t\"'"));
    }

    public function testTheLandingPageNamesTheOccasionsPeopleSearchFor(): void
    {
        $text = strtolower(strip_tags((string) $this->get('/')->getBody()));
        foreach (['birthday', 'anniversary', 'wedding', 'boyfriend', 'girlfriend', 'monthsary', 'invitation'] as $word) {
            $this->assertStringContainsString($word, $text, $word);
        }
        $this->assertStringContainsString('href="/invitations"', (string) $this->get('/')->getBody(), 'the invitations page is linked from the landing page');
    }

    public function testTheLandingPageDescribesItselfToSearchEnginesWithTheFaqAndThePrices(): void
    {
        $html = (string) $this->get('/')->getBody();
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $graph = json_decode(html_entity_decode($m[1]), true, 512, JSON_THROW_ON_ERROR)['@graph'];
        $types = array_column($graph, '@type');
        $this->assertSame(['Organization', 'WebSite', 'WebApplication', 'FAQPage'], $types);

        $faq = $graph[3]['mainEntity'];
        $this->assertSame(substr_count($html, '<summary>'), count($faq), 'the schema repeats every question the page prints');
        foreach ($faq as $qa) {
            $this->assertStringContainsString(htmlspecialchars($qa['name'], ENT_NOQUOTES), $html);
        }

        $offers = $graph[2]['offers'];
        $this->assertNotEmpty($offers);
        $sweet = array_values(array_filter($offers, static fn (array $o): bool => str_starts_with($o['name'], 'Sweet plan, per month')))[0];
        $this->assertSame(number_format(config('saas.plans.sweet.prices.month') / 100, 2, '.', ''), $sweet['price'], 'prices come from the plans');
        $this->assertSame('PHP', $sweet['priceCurrency']);
    }

    public function testTheInvitationsPageHasBreadcrumbsAndItsOwnStructuredData(): void
    {
        $html = (string) $this->get('/invitations')->getBody();
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $graph = json_decode(html_entity_decode($m[1]), true, 512, JSON_THROW_ON_ERROR)['@graph'];
        $this->assertSame(['WebPage', 'BreadcrumbList'], array_column($graph, '@type'));
        $this->assertSame('https://love.test/invitations', $graph[0]['url']);
        $this->assertSame(2, count($graph[1]['itemListElement']));
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
        $this->assertSame(['https://love.test/', 'https://love.test/invitations', 'https://love.test/birthday', 'https://love.test/wedding', 'https://love.test/anniversary', 'https://love.test/boyfriend-girlfriend', 'https://love.test/monthsary', 'https://love.test/long-distance', 'https://love.test/terms', 'https://love.test/privacy'], $listed);

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

    public function testTheInvitationsPageSellsTheInvitationTemplatesAndMakesNoPromiseItCannotKeep(): void
    {
        $response = $this->get('/invitations');
        $html = (string) $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('<meta name="robots" content="index, follow"', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://love.test/invitations"', $html);
        foreach (['Tired of printing invitations?', 'Wedding Invitation', 'Christening', 'Debut', 'Baby Shower', 'Sweet', 'Sweetheart', 'Forever'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        // the Wedding Invitation is on Sweet; the Christening needs Sweetheart
        $this->assertMatchesRegularExpression('/Wedding Invitation<\/h3>\s*<p>[^<]*<\/p>\s*<p class="inv-plan">On Sweet and above/', $html);
        $this->assertMatchesRegularExpression('/Christening<\/h3>\s*<p>[^<]*<\/p>\s*<p class="inv-plan">On Sweetheart and above/', $html);
        // nothing here collects replies, so the page says it does not
        $this->assertStringContainsString('Not yet. The invitation tells guests by when to let you know', $html);
        $this->assertStringNotContainsStringIgnoringCase('cost less', $html);
        $this->assertStringContainsString('/demo?layout=envelope&amp;theme=blush&amp;occasion=invitation', $html, 'the hero links to a sample invitation');
    }

    public function testTheInvitationDemoIsAddressedToTheGuestsAndOpensWithOneTap(): void
    {
        $html = (string) $this->get('/demo?layout=classic&occasion=invitation')->getBody();
        $this->assertStringContainsString('An invitation for', $html);
        $this->assertStringNotContainsString('A letter for', $html);
        $this->assertStringContainsString('"occasion":"invitation"', $html);
        $this->assertStringContainsString('Our family and friends', $html);

        $plain = (string) $this->get('/demo?layout=classic')->getBody();
        $this->assertStringContainsString('A letter for', $plain, 'the love-letter demo keeps its wording');
    }
}

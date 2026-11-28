<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Services\Designs;
use App\Services\OccasionPages;
use Naluz\Tests\TestCase;

/** /birthday, /wedding, /anniversary and /boyfriend-girlfriend: one page per occasion, each with its own words, templates and markup. */
final class OccasionPagesTest extends TestCase
{
    public function testEveryOccasionOfTheAppHasAPageExceptInvitationsWhichHasItsOwn(): void
    {
        $covered = array_values(array_unique(array_column(array_filter(OccasionPages::PAGES, static fn (array $p): bool => empty($p['audience'])), 'occasion')));
        sort($covered);
        $expected = array_diff(array_keys(Designs::OCCASIONS), ['invitation']);
        sort($expected);
        $this->assertSame($expected, $covered, 'a new occasion in Designs::OCCASIONS needs its page in OccasionPages');
        foreach (OccasionPages::PAGES as $slug => $copy) {
            $asset = $copy['asset'] ?? $copy['occasion'];
            foreach (['assets/occasions/' . ($copy['mascot'] ?? $asset) . '-lettie.svg', 'assets/occasions/' . $asset . '-phone.jpg'] as $file) {
                $this->assertFileExists(base_path('public/' . $file), $slug);
            }
        }
    }

    public function testEachPageIsIndexableWithItsOwnTitleDescriptionAndCanonical(): void
    {
        $titles = $descriptions = [];
        foreach (OccasionPages::slugs() as $slug) {
            $response = $this->get('/' . $slug);
            $this->assertSame(200, $response->getStatusCode(), $slug);
            $html = (string) $response->getBody();
            $this->assertStringContainsString('<meta name="robots" content="index, follow"', $html, $slug);
            $this->assertStringContainsString('<link rel="canonical" href="https://love.test/' . $slug . '" />', $html, $slug);
            $this->assertSame(1, substr_count($html, '<meta name="description"'), $slug);
            $this->assertSame(1, substr_count($html, '<h1'), $slug);
            preg_match('#<title>(.*?)</title>#', $html, $t);
            preg_match('#<meta name="description" content="(.*?)"#', $html, $d);
            $this->assertLessThanOrEqual(65, mb_strlen(html_entity_decode($t[1])), "{$slug} title");
            $this->assertLessThanOrEqual(160, mb_strlen(html_entity_decode($d[1])), "{$slug} description");
            $this->assertStringNotContainsString('{brand}', $html, $slug);
            $titles[] = $t[1];
            $descriptions[] = $d[1];
        }
        $this->assertCount(count(OccasionPages::slugs()), array_unique($titles));
        $this->assertCount(count(OccasionPages::slugs()), array_unique($descriptions));
    }

    public function testEachPageListsItsOccasionsTemplatesWithThePlanThatHasThem(): void
    {
        $this->seasons('2026-07-01');
        foreach (OccasionPages::PAGES as $slug => $copy) {
            $html = (string) $this->get('/' . $slug)->getBody();
            foreach ($copy['templates'] ?? Designs::OCCASION_DESIGNS[$copy['occasion']] as $key => [$name]) {
                if (empty(Designs::LAYOUTS[$key]['season'])) {
                    $this->assertStringContainsString('<h3>' . htmlspecialchars($name, ENT_NOQUOTES) . '</h3>', $html, "{$slug}: {$name}");
                    $this->assertStringContainsString('href="/demo?layout=' . $key . '"', $html, "{$slug}: {$name}");
                }
            }
            $this->assertStringNotContainsString('Haunted Love', $html, 'a seasonal template only inside its window');
            $plan = ($copy['lang'] ?? 'en') === 'en' ? '#On (Sweet|Sweetheart|Forever) and above#' : '#(Sweet|Sweetheart|Forever) pataas#';
            $this->assertMatchesRegularExpression($plan, $html, $slug);
        }
    }

    public function testTheMonthsaryAndLongDistancePagesHaveTheirOwnIdeasAndTemplates(): void
    {
        $monthsary = (string) $this->get('/monthsary')->getBody();
        $this->assertStringContainsString('What to write in a monthsary message', $monthsary);
        $this->assertStringContainsString('<h3>Sweet Note</h3>', $monthsary, 'the boyfriend/girlfriend templates');
        $long = (string) $this->get('/long-distance')->getBody();
        $this->assertStringContainsString('Miles apart. One link away.', $long);
        $this->assertStringContainsString('Ideas for the one you miss', $long);
        $this->assertStringNotContainsString('gcash', strtolower($long), 'nothing promised about paying from abroad');
        foreach (['/boyfriend-girlfriend', '/birthday'] as $other) {
            $this->assertStringNotContainsString('id="ideas"', (string) $this->get($other)->getBody(), $other . ' has no ideas section');
        }
    }

    public function testThePagesCarryBreadcrumbsAndLinkToTheirSiblings(): void
    {
        foreach (OccasionPages::slugs() as $slug) {
            $html = (string) $this->get('/' . $slug)->getBody();
            preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
            $graph = json_decode(html_entity_decode($m[1]), true, 512, JSON_THROW_ON_ERROR)['@graph'];
            $this->assertSame(['WebPage', 'BreadcrumbList'], array_column($graph, '@type'), $slug);
            $this->assertSame('https://love.test/' . $slug, $graph[0]['url']);
            foreach (array_diff(OccasionPages::slugs(), [$slug]) as $other) {
                $this->assertStringContainsString('href="/' . $other . '"', $html, "{$slug} links to {$other}");
            }
            $this->assertStringContainsString('href="/invitations"', $html, $slug);
        }
    }

    public function testTheLandingPageLinksEveryOccasionCardToItsPage(): void
    {
        $html = (string) $this->get('/')->getBody();
        foreach (OccasionPages::slugs() as $slug) {
            $this->assertStringContainsString('href="/' . $slug . '"', $html, $slug);
        }
        $this->assertStringContainsString('href="/invitations"', $html);
    }

    public function testThePagesDoNotPromiseWhatTheAppDoesNot(): void
    {
        foreach (OccasionPages::slugs() as $slug) {
            $text = strtolower(strip_tags((string) $this->get('/' . $slug)->getBody()));
            foreach (['rsvp', 'guest list', 'testimonial', 'the best ', '#1', 'cheapest', 'costs less'] as $claim) {
                $this->assertStringNotContainsString($claim, $text, "{$slug}: {$claim}");
            }
        }
    }

    public function testTheChristmasPageOffersTheSnowGlobeOnlyInItsWindow(): void
    {
        $this->seasons('2026-11-25');
        $open = (string) $this->get('/christmas')->getBody();
        $this->assertStringContainsString('<h3>Christmas With You</h3>', $open);
        $this->assertStringContainsString('href="/demo?layout=christmas"', $open);
        $this->assertStringContainsString('What to write in a Christmas love letter', $open);

        $this->seasons('2026-07-01');
        $closed = $this->get('/christmas');
        $this->assertSame(200, $closed->getStatusCode(), 'the address stays up all year, for search engines');
        $this->assertStringNotContainsString('<h3>Christmas With You</h3>', (string) $closed->getBody());
        $this->assertStringContainsString('25 November', (string) $closed->getBody(), 'says when the snow globe returns');
        $this->assertStringContainsString('<loc>https://love.test/christmas</loc>', (string) $this->get('/sitemap.xml')->getBody());
    }

    public function testTheChristmasPageWordingMatchesTheConfiguredWindow(): void
    {
        [$month, $day, $lead] = config('saas.seasons.events.christmas');
        $opens = (new \DateTimeImmutable('2026-01-01'))->setDate(2026, $month, $day)->modify("-{$lead} days");
        $this->assertSame('25 November', $opens->format('j F'));
    }

    public function testTheTagalogPageIsDeclaredAndWrittenInFilipino(): void
    {
        $this->seasons(always: true);
        $html = (string) $this->get('/tagalog-monthsary')->getBody();
        $this->assertStringContainsString('<html lang="fil">', $html);
        $this->assertStringContainsString('"inLanguage":"fil"', $html);
        $text = html_entity_decode(strip_tags((string) preg_replace('#<(script|style|svg)\b.*?</\1>#s', '', $html)), ENT_QUOTES);
        foreach (
            ['No app to install', 'Private by default', 'Yours to change', 'Free to start', 'Open a sample', 'Try the template', 'and above', 'In season now',
            'Free to build.', 'Most popular', 'photos included', 'Link and printable QR code', 'No renewals', 'Start with', 'Good to know', 'Do they need an app',
            'Make it tonight', 'More occasions', 'Sign in', 'Start free', 'See a sample', 'Build and preview', 'Every template', 'A sample page', 'Your page online',
            'Pay once', 'Write it tonight', 'A handwritten-style letter', 'main page', 'Made with love'] as $english
        ) {
            $this->assertStringNotContainsString($english, $text, $english);
        }
        $this->assertStringNotContainsString('<small>a month</small>', $html);
        $this->assertStringContainsString('<html lang="en">', (string) $this->get('/monthsary')->getBody(), 'the other pages stay English');
    }

    public function testTheMothersDayPageDoesNotBorrowBirthdayTemplates(): void
    {
        $this->seasons(always: true);
        $html = (string) $this->get('/mothers-day')->getBody();
        foreach (Designs::OCCASION_DESIGNS['birthday'] as [$name]) {
            $this->assertStringNotContainsString('<h3>' . htmlspecialchars($name, ENT_NOQUOTES) . '</h3>', $html, $name);
        }
        $this->assertStringContainsString('href="/demo?layout=envelope"', $html);
        $this->assertStringNotContainsString('layout=christmas', $html);
    }

    public function testTheMonthsaryTitleSpeaksToBothPartnersAndLeavesLoveLetterToItsSibling(): void
    {
        $this->assertStringContainsString('<title>Monthsary message for your boyfriend or girlfriend</title>', (string) $this->get('/monthsary')->getBody());
        $this->assertStringNotContainsString('monthsary', strtolower(OccasionPages::PAGES['boyfriend-girlfriend']['description']));
    }

    public function testAnUnknownPathIsStillANotFound(): void
    {
        $this->assertSame(404, $this->get('/valentine-gifts')->getStatusCode());
    }
}

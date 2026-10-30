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
        $covered = array_column(OccasionPages::PAGES, 'occasion');
        sort($covered);
        $expected = array_diff(array_keys(Designs::OCCASIONS), ['invitation']);
        sort($expected);
        $this->assertSame($expected, $covered, 'a new occasion in Designs::OCCASIONS needs its page in OccasionPages');
        foreach (OccasionPages::PAGES as $slug => $copy) {
            foreach (['assets/occasions/' . $copy['occasion'] . '-lettie.svg', 'assets/occasions/' . $copy['occasion'] . '-phone.jpg'] as $file) {
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
        $this->assertCount(4, array_unique($titles));
        $this->assertCount(4, array_unique($descriptions));
    }

    public function testEachPageListsItsOccasionsTemplatesWithThePlanThatHasThem(): void
    {
        $this->seasons('2026-07-01');
        foreach (OccasionPages::PAGES as $slug => $copy) {
            $html = (string) $this->get('/' . $slug)->getBody();
            foreach (Designs::OCCASION_DESIGNS[$copy['occasion']] as $key => [$name]) {
                if (empty(Designs::LAYOUTS[$key]['season'])) {
                    $this->assertStringContainsString('<h3>' . htmlspecialchars($name, ENT_NOQUOTES) . '</h3>', $html, "{$slug}: {$name}");
                    $this->assertStringContainsString('href="/demo?layout=' . $key . '"', $html, "{$slug}: {$name}");
                }
            }
            $this->assertStringNotContainsString('Haunted Love', $html, 'a seasonal template only inside its window');
            $this->assertMatchesRegularExpression('#On (Sweet|Sweetheart|Forever) and above#', $html, $slug);
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

    public function testAnUnknownPathIsStillANotFound(): void
    {
        $this->assertSame(404, $this->get('/valentine-gifts')->getStatusCode());
    }
}

<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use Naluz\Config\Repository;
use Naluz\Tests\TestCase;

/** The Terms and the Privacy Policy are public, indexable, say who runs the service, and are linked from where people sign up. */
final class LegalPagesTest extends TestCase
{
    public function testBothPagesArePublicAndIndexable(): void
    {
        foreach (['/terms' => 'Terms and Conditions', '/privacy' => 'Privacy Policy'] as $uri => $title) {
            $response = $this->get($uri);
            $html = (string) $response->getBody();
            $this->assertSame(200, $response->getStatusCode(), $uri);
            $this->assertStringContainsString('<h1 class="display">' . $title . '</h1>', $html, $uri);
            $this->assertStringContainsString('<meta name="robots" content="index, follow"', $html, $uri);
            $this->assertStringNotContainsString('googletagmanager', $html, 'tests carry no analytics tag');
        }
    }

    public function testTheOperatorAndContactComeFromConfig(): void
    {
        $this->app->make(Repository::class)->set('saas.legal.operator', 'Acme Love Inc.');
        $this->app->make(Repository::class)->set('saas.legal.email', 'privacy@acme.test');
        $this->app->make(Repository::class)->set('saas.legal.dpo', 'Dana Cruz');
        foreach (['/terms', '/privacy'] as $uri) {
            $html = (string) $this->get($uri)->getBody();
            $this->assertStringContainsString('Acme Love Inc.', $html, $uri);
            $this->assertStringContainsString('privacy@acme.test', $html, $uri);
        }
        $this->assertStringContainsString('Dana Cruz', (string) $this->get('/privacy')->getBody());
    }

    /** The policy must describe what the app really does: these are the facts it states, kept in step with the code. */
    public function testThePolicyDescribesWhatTheAppDoes(): void
    {
        $privacy = (string) $this->get('/privacy')->getBody();
        foreach ([
            'Republic Act No. 10173', 'National Privacy Commission', 'Argon2id', 'Google Analytics', 'PayMongo', 'Xendit', 'YouTube',
            'naluz_session', 'data portability', 'within the time the law requires',
        ] as $fact) {
            $this->assertStringContainsString($fact, $privacy, $fact);
        }
        $this->assertSame('naluz_session', config('session.cookie'), 'the cookie the policy names');
        $this->assertSame(7200, config('session.lifetime'), 'the policy says two hours');
    }

    public function testTheTermsCoverTheMoneyAndTheRules(): void
    {
        $terms = (string) $this->get('/terms')->getBody();
        foreach (['at least 16', 'parent or legal guardian', 'Automatic renewal', 'No refunds', 'all plans and subscriptions', 'Reports and takedowns', 'laws of the Republic of the Philippines'] as $text) {
            $this->assertStringContainsString($text, $terms, $text);
        }
        $this->assertSame(['₱1,000'], array_values(array_unique(preg_match_all('/₱[\d,]+/u', $terms, $m) ? $m[0] : [])), 'only the liability floor is a peso amount: plan prices would drift from config/saas.php');
    }

    public function testTheyAreLinkedAndInTheSitemap(): void
    {
        foreach (['/', '/register'] as $uri) {
            $html = (string) $this->get($uri)->getBody();
            $this->assertStringContainsString('href="/terms"', $html, $uri);
            $this->assertStringContainsString('href="/privacy"', $html, $uri);
        }
        $sitemap = (string) $this->get('/sitemap.xml')->getBody();
        $this->assertStringContainsString('<loc>https://love.test/terms</loc>', $sitemap);
        $this->assertStringContainsString('<loc>https://love.test/privacy</loc>', $sitemap);
    }

    public function testTheAppIconIsAStoreReadySquare(): void
    {
        $file = base_path('marketing/brand/app-icon-1024.png');
        $this->assertFileExists($file);
        $size = getimagesize($file);
        $this->assertSame([1024, 1024], [$size[0], $size[1]]);
        $this->assertSame('image/png', $size['mime']);
        $this->assertSame(2, ord((string) file_get_contents($file, false, null, 25, 1)), 'PNG colour type 2 = RGB, no alpha channel');
    }
}

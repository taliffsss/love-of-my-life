<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Models\User;
use Naluz\Tests\TestCase;

/** The Google tag is on the marketing site, sign-in and dashboard, and nowhere a private page or the admin area is shown. */
final class AnalyticsTest extends TestCase
{
    private const ID = 'G-M62J7X95ED';

    public function testTheTagIsPrintedWhenAnIdIsSet(): void
    {
        $this->analytics(self::ID);
        foreach (['/', '/login', '/register'] as $uri) {
            $html = (string) $this->get($uri)->getBody();
            $this->assertStringNotContainsString('googletagmanager', $html, "{$uri}: Google's script is fetched after the page has loaded, not in the head");
            $this->assertMatchesRegularExpression('#<script src="/assets/analytics\.js\?v=\d*" data-id="' . self::ID . '" defer></script>#', $html, $uri);
        }
    }

    public function testNothingIsPrintedWithoutAnId(): void
    {
        $this->analytics('');
        $html = (string) $this->get('/')->getBody();
        $this->assertStringNotContainsString('googletagmanager', $html);
        $this->assertStringNotContainsString('analytics.js', $html);
    }

    public function testThePagesPeopleMakeAndTheAdminAreaHaveNoTag(): void
    {
        $this->analytics(self::ID);
        $this->assertStringNotContainsString('googletagmanager', (string) $this->get('/demo')->getBody(), 'a page made for someone is not tracked');

        $admin = new User();
        $admin->forceFill(['name' => 'Boss', 'email' => 'boss@example.com', 'password' => 'correct horse', 'is_admin' => true])->save();
        $this->signIn($admin);
        $response = $this->get('/admin');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringNotContainsString('googletagmanager', (string) $response->getBody());
    }

    public function testTheStartUpScriptIsAFileSoTheCspNeedsNoInlineScripts(): void
    {
        $js = (string) file_get_contents(base_path('public/assets/analytics.js'));
        $this->assertStringContainsString("gtag('js', new Date())", $js);
        $this->assertStringContainsString("gtag('config', id)", $js);
        $this->assertStringContainsString('dataset.id', $js);
        $this->assertStringContainsString("https://www.googletagmanager.com/gtag/js?id=", $js, 'the script that loads the tag');
        $this->assertStringContainsString("addEventListener('load', arm", $js, 'and only once the page has loaded');
        $this->assertStringContainsString("'pointerdown'", $js, 'after the visitor does something');
        $this->assertStringContainsString('setTimeout(load, 5000)', $js, 'or after five seconds');
    }

    public function testTheCspAllowsGoogleOnlyWhileAnalyticsIsOn(): void
    {
        $with = $this->security('G-TEST');
        $this->assertStringContainsString("script-src 'self' https://www.youtube.com https://www.googletagmanager.com", $with);
        $this->assertStringContainsString('https://*.google-analytics.com', $with);
        $this->assertStringContainsString("connect-src 'self'", $with);

        $without = $this->security('');
        $this->assertStringNotContainsString('googletagmanager', $without);
        $this->assertStringNotContainsString('google-analytics', $without);
    }

    private function analytics(string $id): void
    {
        $this->app->make(\Naluz\Config\Repository::class)->set('saas.analytics_id', $id);
    }

    private function security(string $id): string
    {
        $previous = getenv('GA_MEASUREMENT_ID');
        putenv('GA_MEASUREMENT_ID=' . $id);
        $_ENV['GA_MEASUREMENT_ID'] = $_SERVER['GA_MEASUREMENT_ID'] = $id;
        try {
            return (string) (require base_path('config/security.php'))['headers']['Content-Security-Policy'];
        } finally {
            $previous === false ? putenv('GA_MEASUREMENT_ID') : putenv('GA_MEASUREMENT_ID=' . $previous);
            unset($_ENV['GA_MEASUREMENT_ID'], $_SERVER['GA_MEASUREMENT_ID']);
        }
    }
}

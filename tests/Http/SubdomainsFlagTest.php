<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Models\Page;
use App\Services\Plans;
use Naluz\Config\Repository;
use Naluz\Tests\TestCase;

/** Own addresses (<name>.<domain>) for Sweetheart and Forever are behind the `subdomains` feature flag, and off by default. */
final class SubdomainsFlagTest extends TestCase
{
    private function flag(bool $on): void
    {
        $this->app->make(Repository::class)->set('saas.features.subdomains', $on);
    }

    public function testTheFlagIsOffByDefaultInTheConfig(): void
    {
        $config = require base_path('config/saas.php');
        $this->assertFalse($config['features']['subdomains'], 'SUBDOMAINS_ENABLED is unset in tests: off');
        $this->assertTrue($config['plans']['sweetheart']['subdomain'] && $config['plans']['forever']['subdomain'], 'the plans keep the entitlement');
        $this->assertFalse($config['plans']['sweet']['subdomain']);
    }

    public function testNoPlanIncludesAnAddressWhileItIsOff(): void
    {
        $this->flag(false);
        $plans = $this->app->make(Plans::class)->all();
        foreach (['free', 'sweet', 'sweetheart', 'forever'] as $key) {
            $this->assertFalse($plans[$key]['subdomain'], $key);
            $this->assertStringNotContainsString('address', $plans[$key]['blurb'], $key);
        }
        $this->flag(true);
        $plans = $this->app->make(Plans::class)->all();
        $this->assertTrue($plans['sweetheart']['subdomain'] && $plans['forever']['subdomain']);
        $this->assertFalse($plans['sweet']['subdomain']);
        $this->assertStringContainsString('an address of its own', $plans['sweetheart']['blurb']);
    }

    public function testThePublicSiteDoesNotSellAnAddressWhileItIsOff(): void
    {
        $this->flag(false);
        $html = (string) $this->get('/')->getBody();
        $this->assertStringNotContainsString('yourname.love.test', $html);
        $this->assertStringNotContainsString('Its own address', $html);
        $this->assertStringNotContainsString('What is the dedicated address?', $html);
        $this->assertStringContainsString('Sweetheart', $html, 'the plan itself is still sold');

        $this->flag(true);
        $html = (string) $this->get('/')->getBody();
        $this->assertStringContainsString('Its own address', $html);
        $this->assertStringContainsString('What is the dedicated address?', $html);
    }

    public function testTheDashboardAndItsRoutesAreClosedWhileItIsOff(): void
    {
        $owner = $this->makeUser();
        $page = $this->makeLivePage($owner, ['plan' => 'sweetheart']);
        $this->signIn($owner);

        $this->flag(true);
        $this->assertStringContainsString('Dedicated address', (string) $this->get('/dashboard/pages/' . $page->slug)->getBody());

        $this->flag(false);
        $this->assertStringNotContainsString('Dedicated address', (string) $this->get('/dashboard/pages/' . $page->slug)->getBody());
        $this->assertSame(404, $this->get('/dashboard/subdomains/check?name=nice')->getStatusCode());
        $this->assertSame(404, $this->submit('PUT', '/dashboard/pages/' . $page->slug . '/subdomain', ['subdomain' => 'nice'])->getStatusCode());
        $this->assertNull($page->fresh()->subdomain);
    }

    public function testAClaimedAddressIsNotServedWhileItIsOff(): void
    {
        $page = $this->makeLivePage($this->makeUser(), ['plan' => 'forever', 'subdomain' => 'nice']);
        $this->flag(true);
        $this->assertSame(200, $this->get('https://nice.love.test/')->getStatusCode());
        $this->flag(false);
        $this->assertSame(404, $this->get('https://nice.love.test/')->getStatusCode(), 'kept in the database, not served');
        $this->assertFalse(Page::query()->find($page->id)->allowsSubdomain());
        $this->assertStringNotContainsString('nice.love.test', $this->app->make(\App\Services\Sites::class)->url($page));
    }
}

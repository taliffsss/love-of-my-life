<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Models\Page;
use App\Models\User;
use Naluz\Security\Hasher;
use Naluz\Tests\TestCase;

final class SubdomainTest extends TestCase
{
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->makeUser();
    }

    private function claim(Page $page, string $name): void
    {
        $this->submit('PUT', '/dashboard/pages/' . $page->slug . '/subdomain', ['subdomain' => $name]);
    }

    // ------------------------------------------------------------------ claiming from the dashboard

    public function testOnlyForeverPagesGetAnAddressOfTheirOwn(): void
    {
        $this->signIn($this->owner);
        $basic = $this->makeLivePage($this->owner, ['plan' => 'basic', 'expires_at' => '2099-01-01 00:00:00']);
        $response = $this->submit('PUT', '/dashboard/pages/' . $basic->slug . '/subdomain', ['subdomain' => 'jinelin']);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertNull($basic->fresh()->subdomain);
    }

    public function testAForeverPageClaimsANameAndItWorksImmediately(): void
    {
        $this->signIn($this->owner);
        $page = $this->makeLivePage($this->owner, ['recipient' => 'Jinelin']);
        $this->claim($page, '  JineLin.love.test ');

        $this->assertSame('jinelin', $page->fresh()->subdomain, 'typed in any case, even as the whole address');
        $html = (string) $this->get('https://jinelin.love.test/')->getBody();
        $this->assertStringContainsString('For Jinelin', $html);
        $this->assertStringContainsString('https://jinelin.love.test/', (string) $this->get('/dashboard/pages/' . $page->slug)->getBody(), 'the dashboard shares the new address');
    }

    public function testBadNamesAreRefused(): void
    {
        $this->signIn($this->owner);
        $page = $this->makeLivePage($this->owner);
        foreach (['ab', 'www', 'admin', '-jinelin', 'jinelin-', 'jin--elin', 'xn--abc', 'jinelin.basela', 'jine_lin', 'jinélin', str_repeat('a', 31)] as $bad) {
            $this->claim($page, $bad);
            $this->assertNull($page->fresh()->subdomain, "[{$bad}] must be refused");
        }
    }

    public function testANameCanOnlyBelongToOnePage(): void
    {
        $this->makeLivePage($this->makeUser('other@example.com'), ['subdomain' => 'jinelin']);
        $this->signIn($this->owner);
        $page = $this->makeLivePage($this->owner);

        $this->claim($page, 'jinelin');
        $this->assertNull($page->fresh()->subdomain);

        $check = $this->decode($this->get('/dashboard/subdomains/check?name=Jinelin', ['Accept' => 'application/json']));
        $this->assertFalse($check['available']);
        $this->assertSame('That address is already taken.', $check['message']);
        $free = $this->decode($this->get('/dashboard/subdomains/check?name=yam-and-me', ['Accept' => 'application/json']));
        $this->assertTrue($free['available']);
        $this->assertSame('Available: https://yam-and-me.love.test/', $free['message']);
    }

    public function testChangingAndReleasingTheName(): void
    {
        $this->signIn($this->owner);
        $page = $this->makeLivePage($this->owner, ['subdomain' => 'first']);
        $this->claim($page, 'second');
        $this->assertSame('second', $page->fresh()->subdomain);
        $this->assertSame(404, $this->get('https://first.love.test/')->getStatusCode(), 'the old name is free again');

        $this->submit('DELETE', '/dashboard/pages/' . $page->slug . '/subdomain');
        $this->assertNull($page->fresh()->subdomain);
        $this->assertSame(404, $this->get('https://second.love.test/')->getStatusCode());
        $this->assertSame(200, $this->get('/p/' . $page->slug)->getStatusCode(), 'the /p/ link keeps working');
    }

    // ------------------------------------------------------------------ serving <name>.<domain>

    public function testTheSubdomainServesOnlyItsPage(): void
    {
        $page = $this->makeLivePage($this->owner, ['subdomain' => 'jinelin', 'gate_enabled' => true, 'gate_password' => $this->app->make(Hasher::class)->make('yam')]);
        $this->makePhoto($page, 'https://cdn.test/hidden.jpg');

        $html = (string) $this->get('https://jinelin.love.test/')->getBody();
        $this->assertStringContainsString('"unlockUrl":"\/unlock"', $html, 'unlocks on its own host');
        $this->assertStringNotContainsString('hidden.jpg', $html);

        $token = $this->csrf('https://jinelin.love.test/');
        $open = $this->json('POST', 'https://jinelin.love.test/unlock', ['answer' => 'Yam'], ['X-CSRF-TOKEN' => $token]);
        $this->assertSame(200, $open->getStatusCode());
        $this->assertSame(['https://cdn.test/hidden.jpg'], $this->decode($open)['photos']);

        foreach (['/dashboard', '/login', '/register', '/demo', '/p/' . $page->slug, '/api/photos', '/anything'] as $path) {
            $this->assertSame(404, $this->get('https://jinelin.love.test' . $path)->getStatusCode(), "{$path} is not served on a page's own address");
        }
        $this->assertSame("User-agent: *\nDisallow: /\n", (string) $this->get('https://jinelin.love.test/robots.txt')->getBody());
        $this->assertSame(200, $this->get('https://jinelin.love.test/api/ping')->getStatusCode(), 'the health check answers on every host');
    }

    public function testUnknownAndNestedSubdomainsAreNotFound(): void
    {
        $this->makeLivePage($this->owner, ['subdomain' => 'jinelin']);
        $this->assertSame(404, $this->get('https://nobody.love.test/')->getStatusCode());
        $this->assertSame(404, $this->get('https://a.jinelin.love.test/')->getStatusCode());
    }

    public function testADraftOnASubdomainIsNotShown(): void
    {
        // a subdomain left on a page that is no longer on a plan with one (e.g. changed by an admin) stops resolving
        $this->makePage($this->owner, ['subdomain' => 'draft', 'plan' => 'free']);
        $this->assertSame(404, $this->get('https://draft.love.test/')->getStatusCode());
    }

    public function testTheProductStillAnswersOnItsOwnDomain(): void
    {
        $this->makeLivePage($this->owner, ['subdomain' => 'jinelin']);
        foreach (['https://love.test/', 'https://www.love.test/', 'http://127.0.0.1/', '/'] as $url) {
            $this->assertStringContainsString('A love letter they', (string) $this->get($url)->getBody(), $url);
        }
        $this->assertSame(200, $this->get('https://love.test/api/ping')->getStatusCode());
    }

    public function testACustomDomainServesItsPage(): void
    {
        $page = $this->makeLivePage($this->owner, ['custom_domain' => 'jinelin-basela.example.com', 'recipient' => 'Jinelin Basela', 'searchable' => true]);
        $html = (string) $this->get('https://jinelin-basela.example.com/')->getBody();
        $this->assertStringContainsString('For Jinelin Basela', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://jinelin-basela.example.com/"', $html);
        $this->assertStringContainsString('Sitemap: https://jinelin-basela.example.com/sitemap.xml', (string) $this->get('https://jinelin-basela.example.com/robots.txt')->getBody());
        $this->assertSame(404, $this->get('https://jinelin-basela.example.com/dashboard')->getStatusCode());
        $this->assertNotNull($page->fresh());
    }

    public function testAnUnknownHostIsTheProduct(): void
    {
        $this->assertStringContainsString('A love letter they', (string) $this->get('https://unknown.example.org/')->getBody());
    }
}

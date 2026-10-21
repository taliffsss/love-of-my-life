<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Models\Order;
use App\Models\User;
use App\Services\Traffic;
use Naluz\Tests\TestCase;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;

final class TrafficTest extends TestCase
{
    private const AGENT = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Safari/604.1';

    /** @param array<string,string> $cookies @param array<string,string> $headers */
    private function visit(string $uri, array $cookies = [], array $headers = []): ResponseInterface
    {
        $request = (new ServerRequest('GET', 'https://love.test' . $uri, ['User-Agent' => self::AGENT] + $headers))->withCookieParams($cookies);
        return $this->send($request);
    }

    /** @return array<string,string> the cookies a response sets */
    private function cookies(ResponseInterface $response): array
    {
        $out = [];
        foreach ($response->getHeader('Set-Cookie') as $line) {
            [$pair] = explode(';', $line, 2);
            [$name, $value] = explode('=', $pair, 2);
            $out[$name] = $value;
        }
        return $out;
    }

    private function visits(): array
    {
        return $this->db()->table('visits')->get()->all();
    }

    public function testATaggedVisitIsRecordedAndSetsTheVisitorAndCampaignCookies(): void
    {
        $response = $this->visit('/?utm_source=Facebook&utm_medium=paid_social&utm_campaign=halloween-2026&utm_content=ad-hw-1');
        $this->assertSame(200, $response->getStatusCode());

        [$visit] = $this->visits();
        $this->assertSame(['facebook', 'paid_social', 'halloween-2026', 'ad-hw-1', '/'], [$visit['source'], $visit['medium'], $visit['campaign'], $visit['content'], $visit['path']]);
        $cookies = $this->cookies($response);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $cookies[Traffic::VISITOR]);
        $this->assertArrayHasKey(Traffic::ATTRIBUTION, $cookies);
        $this->assertStringNotContainsString('127.0.0.1', json_encode($visit), 'no IP address is kept');
    }

    public function testTagsAreCleanedBeforeTheyAreStored(): void
    {
        $this->visit('/?utm_source=<script>alert(1)</script>&utm_medium=' . urlencode('a b') . '&utm_campaign=' . str_repeat('x', 200));
        [$visit] = $this->visits();
        $this->assertSame('script-alert-1-script', $visit['source']);
        $this->assertSame('a-b', $visit['medium']);
        $this->assertSame(60, strlen($visit['campaign']));
    }

    public function testTheSameVisitorAndCampaignAreCountedOncePerHalfHour(): void
    {
        $first = $this->visit('/?utm_source=facebook&utm_medium=social&utm_campaign=halloween-2026');
        $visitor = $this->cookies($first)[Traffic::VISITOR];
        $this->visit('/?utm_source=facebook&utm_medium=social&utm_campaign=halloween-2026', [Traffic::VISITOR => $visitor]);
        $this->visit('/demo?utm_source=facebook&utm_medium=social&utm_campaign=halloween-2026', [Traffic::VISITOR => $visitor]);
        $this->assertCount(1, $this->visits(), 'a refresh is not a new visit');

        $this->visit('/?utm_source=tiktok&utm_medium=social&utm_campaign=halloween-2026', [Traffic::VISITOR => $visitor]);
        $this->assertCount(2, $this->visits(), 'another source is');
    }

    public function testAVisitFromAnotherSiteIsAReferralAndOwnLinksAreIgnored(): void
    {
        $this->visit('/', [], ['Referer' => 'https://l.facebook.com/l.php?u=x']);
        $this->visit('/demo', [], ['Referer' => 'https://love.test/']);
        $this->visit('/', [], []);
        $rows = array_map(static fn (array $v): string => $v['source'] . '/' . $v['medium'], $this->visits());
        $this->assertContains('facebook/referral', $rows);
        $this->assertContains('direct/none', $rows, 'no referrer, or our own site: direct');
    }

    public function testBotsAndOtherPagesAreNotCounted(): void
    {
        $this->send((new ServerRequest('GET', 'https://love.test/?utm_source=x&utm_medium=y', ['User-Agent' => 'Googlebot/2.1'])));
        $this->send((new ServerRequest('GET', 'https://love.test/?utm_source=x&utm_medium=y', ['User-Agent' => 'facebookexternalhit/1.1'])));
        $this->send((new ServerRequest('GET', 'https://love.test/?utm_source=x&utm_medium=y')));
        $this->assertCount(0, $this->visits());

        $page = $this->makePage($this->makeUser());
        $this->visit('/p/' . $page->slug . '?utm_source=x&utm_medium=y');
        $this->assertCount(0, $this->visits(), 'the pages people make for each other are never tracked');
    }

    public function testTheFirstTouchIsKeptAndSavedOnTheAccountThatSignsUp(): void
    {
        $first = $this->visit('/?utm_source=facebook&utm_medium=paid_social&utm_campaign=halloween-2026&utm_content=ad-hw-1');
        $cookies = $this->cookies($first);
        $later = $this->visit('/?utm_source=tiktok&utm_medium=social&utm_campaign=other', $cookies);
        $this->assertArrayNotHasKey(Traffic::ATTRIBUTION, $this->cookies($later), 'first touch: a later link does not replace it');

        $token = $this->csrf('/register');
        $request = (new ServerRequest('POST', 'https://love.test/register', ['User-Agent' => self::AGENT, 'Content-Type' => 'application/x-www-form-urlencoded']))
            ->withCookieParams($cookies)
            ->withParsedBody(['_token' => $token, 'name' => 'Mark', 'email' => 'mark@example.com', 'password' => 'long enough', 'password_confirmation' => 'long enough']);
        $this->send($request);

        $user = User::query()->where('email', 'mark@example.com')->first();
        $this->assertSame(['facebook', 'paid_social', 'halloween-2026', 'ad-hw-1'], [$user->utm_source, $user->utm_medium, $user->utm_campaign, $user->utm_content]);
    }

    public function testTheAdminSeesWhereTheCustomersCameFrom(): void
    {
        $this->visit('/?utm_source=facebook&utm_medium=paid_social&utm_campaign=halloween-2026&utm_content=ad-hw-1');
        $this->visit('/?utm_source=tiktok&utm_medium=social&utm_campaign=halloween-2026');
        $buyer = $this->makeUser('buyer@example.com');
        $this->db()->table('users')->where('id', '=', $buyer->id)->update(['utm_source' => 'facebook', 'utm_medium' => 'paid_social', 'utm_campaign' => 'halloween-2026', 'utm_content' => 'ad-hw-1']);
        $order = new Order();
        $order->forceFill([
            'reference' => 'LM-AAAA-BBBB', 'user_id' => $buyer->id, 'plan' => 'sweetheart', 'amount' => 35000, 'currency' => 'PHP', 'status' => 'paid',
            'provider' => 'manual', 'period' => 'month', 'photos' => 0, 'paid_at' => gmdate('Y-m-d H:i:s'),
        ])->save();

        $admin = new User();
        $admin->forceFill(['name' => 'Boss', 'email' => 'boss@example.com', 'password' => 'correct horse', 'is_admin' => true])->save();
        $this->signIn($admin);
        $html = (string) $this->get('/admin/traffic')->getBody();

        $this->assertStringContainsString('halloween-2026', $html);
        $this->assertStringContainsString('ad-hw-1', $html);
        $this->assertStringContainsString('₱350</td>', $html, 'the customer and what they paid');
    }

    public function testOnlyAdministratorsSeeTheTraffic(): void
    {
        $this->assertSame('/login', $this->get('/admin/traffic')->getHeaderLine('Location'));
        $this->signIn($this->makeUser('plain@example.com'));
        $this->assertSame(404, $this->get('/admin/traffic')->getStatusCode());
    }
}

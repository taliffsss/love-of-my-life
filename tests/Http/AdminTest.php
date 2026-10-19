<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Models\Order;
use App\Models\User;
use Naluz\Tests\TestCase;

final class AdminTest extends TestCase
{
    private function makeAdmin(): User
    {
        $admin = new User();
        $admin->forceFill(['name' => 'Boss', 'email' => 'boss@example.com', 'password' => 'correct horse', 'is_admin' => true])->save();
        return $admin;
    }

    private function makeOrder(User $user, int $amount, string $status = 'paid'): Order
    {
        $order = new Order();
        $order->forceFill([
            'reference' => 'LM-' . strtoupper(substr(md5((string) mt_rand()), 0, 4)) . '-' . strtoupper(substr(md5((string) mt_rand()), 0, 4)),
            'user_id' => $user->id, 'plan' => 'sweetheart', 'amount' => $amount, 'currency' => 'PHP', 'status' => $status,
            'provider' => 'manual', 'period' => 'year', 'photos' => 0, 'paid_at' => $status === 'paid' ? gmdate('Y-m-d H:i:s') : null,
        ])->save();
        return $order;
    }

    public function testASignedOutVisitorIsSentToSignIn(): void
    {
        foreach (['/admin', '/admin/users'] as $uri) {
            $this->assertSame('/login', $this->get($uri)->getHeaderLine('Location'), $uri);
        }
    }

    public function testAnOrdinaryUserSeesNothingThere(): void
    {
        $this->signIn($this->makeUser());
        foreach (['/admin', '/admin/users'] as $uri) {
            $this->assertSame(404, $this->get($uri)->getStatusCode(), $uri);
        }
    }

    public function testTheNavigationLinkIsOnlyForAdministrators(): void
    {
        $this->signIn($this->makeUser('plain@example.com'));
        $this->assertStringNotContainsString('href="/admin"', (string) $this->get('/dashboard')->getBody());
        $this->submit('POST', '/logout');

        $this->makeAdmin();
        $this->signIn(User::query()->where('email', 'boss@example.com')->first());
        $this->assertStringContainsString('href="/admin"', (string) $this->get('/dashboard')->getBody());
    }

    public function testTheOverviewCountsUsersSubscribersPagesAndRevenue(): void
    {
        $admin = $this->makeAdmin();
        $subscriber = $this->makeUser('sub@example.com');
        $this->makeLivePage($subscriber, ['plan' => 'sweetheart', 'expires_at' => gmdate('Y-m-d H:i:s', time() + 86400 * 30)]);
        $this->makeLivePage($subscriber, ['plan' => 'forever']);
        $lapsed = $this->makeUser('lapsed@example.com');
        $this->makePage($lapsed, ['plan' => 'sweetheart', 'published_at' => '2026-01-01 00:00:00', 'expires_at' => '2026-02-01 00:00:00']);
        $free = $this->makeUser('free@example.com');
        $this->makePage($free);
        $google = new User();
        $google->forceFill(['name' => 'G', 'email' => 'g@example.com', 'password' => 'xxxxxxxxxxxx', 'is_admin' => false, 'login_type' => 'google'])->save();
        $this->makeOrder($subscriber, 29900);
        $this->makeOrder($subscriber, 10000);
        $this->makeOrder($free, 5000, 'pending');

        $this->signIn($admin);
        $html = (string) $this->get('/admin')->getBody();

        $this->assertMatchesRegularExpression('~<span class="n">5</span><span class="l">Users</span>~', $html);
        $this->assertMatchesRegularExpression('~<span class="n">1</span><span class="l">Subscribers</span>~', $html);
        $this->assertMatchesRegularExpression('~<span class="n">4</span><span class="l">Pages</span>~', $html);
        $this->assertStringContainsString('2 live · 1 draft · 1 expired', $html);
        $this->assertStringContainsString('₱399', $html);
        $this->assertStringContainsString('2 paid orders', $html);
        $this->assertMatchesRegularExpression('~Google</td><td class="num"><a href="/admin/users\?type=google">1</a>~', $html);
        $this->assertStringContainsString('Orders waiting for payment</td><td class="num">1<', $html);
    }

    public function testTheUserListSearchesFiltersAndPages(): void
    {
        $admin = $this->makeAdmin();
        $sub = $this->makeUser('paid@example.com');
        $this->makeLivePage($sub, ['plan' => 'forever']);
        $this->makeUser('plain@example.com');
        for ($i = 1; $i <= 22; $i++) {
            $this->makeUser("bulk{$i}@example.com");
        }
        $this->signIn($admin);

        $html = (string) $this->get('/admin/users')->getBody();
        $this->assertStringContainsString('25 users', $html);
        $this->assertStringContainsString('Page 1 of 2', $html);
        $this->assertStringContainsString('page=2', $html);
        $this->assertStringContainsString('bulk1@example.com', (string) $this->get('/admin/users?page=2')->getBody());

        $found = (string) $this->get('/admin/users?q=PAID@')->getBody();
        $this->assertStringContainsString('paid@example.com', $found);
        $this->assertStringNotContainsString('plain@example.com', $found);
        $this->assertStringContainsString('Forever', $found);

        $subs = (string) $this->get('/admin/users?subscribers=1')->getBody();
        $this->assertStringContainsString('paid@example.com', $subs);
        $this->assertStringNotContainsString('plain@example.com', $subs);

        $this->assertStringContainsString('boss@example.com', (string) $this->get('/admin/users?role=admin')->getBody());
        $this->assertStringContainsString('No users match', (string) $this->get('/admin/users?type=facebook')->getBody());
        // a wildcard typed into the search is not a wildcard
        $this->assertStringContainsString('No users match', (string) $this->get('/admin/users?q=%25')->getBody());
    }

    public function testThePaymentsPageListsEveryOrderWithTotalsAndFilters(): void
    {
        $admin = $this->makeAdmin();
        $buyer = $this->makeUser('buyer@example.com');
        $paid = $this->makeOrder($buyer, 35000);
        $this->makeOrder($buyer, 20000, 'pending');
        $this->makeOrder($buyer, 10000, 'cancelled');
        $this->signIn($admin);

        $html = (string) $this->get('/admin/payments')->getBody();
        $this->assertStringContainsString($paid->reference, $html);
        $this->assertStringContainsString('buyer@example.com', $html);
        $this->assertStringContainsString('₱350</span><span class="l">Received', $html);
        $this->assertStringContainsString('1 paid orders', $html);
        $this->assertStringContainsString('₱200 in pending orders', $html);

        $only = (string) $this->get('/admin/payments?status=paid')->getBody();
        $this->assertStringContainsString($paid->reference, $only);
        $this->assertSame(1, substr_count($only, '<span class="pill pill-'));
        $this->assertStringContainsString($paid->reference, (string) $this->get('/admin/payments?q=BUYER@')->getBody());
        $this->assertStringContainsString('No payments match', (string) $this->get('/admin/payments?q=nobody')->getBody());
        $this->assertStringContainsString('No payments match', (string) $this->get('/admin/payments?provider=paymongo')->getBody());
    }

    public function testPaymentTimesAreShownInManilaTime(): void
    {
        $admin = $this->makeAdmin();
        $buyer = $this->makeUser('buyer@example.com');
        $order = $this->makeOrder($buyer, 35000);
        $order->forceFill(['paid_at' => '2026-10-18 08:46:00'])->save();
        $this->signIn($admin);

        $html = (string) $this->get('/admin/payments')->getBody();
        $this->assertStringContainsString('Oct 18, 2026 4:46 PM', $html);
        $this->assertStringNotContainsString('(UTC', $html, 'no zone label under the date');
    }

    public function testThePaymentsPageIsForAdministratorsOnly(): void
    {
        $this->assertSame('/login', $this->get('/admin/payments')->getHeaderLine('Location'));
        $this->signIn($this->makeUser());
        $this->assertSame(404, $this->get('/admin/payments')->getStatusCode());
    }

    public function testTheAdminAreaIsNotCachedOrIndexed(): void
    {
        $this->signIn($this->makeAdmin());
        $response = $this->get('/admin');
        $this->assertStringContainsString('noindex', $response->getHeaderLine('X-Robots-Tag'));
        $this->assertStringContainsString('no-store', $response->getHeaderLine('Cache-Control'));
        $this->assertStringContainsString('Disallow: /admin', (string) $this->get('/robots.txt')->getBody());
    }
}

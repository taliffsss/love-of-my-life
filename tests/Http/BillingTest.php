<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Models\Order;
use App\Models\Page;
use App\Models\User;
use App\Services\Billing;
use Naluz\Tests\TestCase;

/** Plan activation: expiry arithmetic, renewals, upgrades, and exactly-once activation. */
final class BillingTest extends TestCase
{
    private User $owner;
    private Billing $billing;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->makeUser();
        $this->billing = $this->app->make(Billing::class);
        $this->now = new \DateTimeImmutable('2026-10-13 12:00:00', new \DateTimeZone('UTC'));
    }

    private function pay(Page $page, string $plan, ?\DateTimeImmutable $at = null): Order
    {
        $order = $this->billing->createOrder($this->owner->id, $page, $plan, 'manual');
        $this->assertTrue($this->billing->markPaid($order, 'manual', null, $at ?? $this->now));
        return $order;
    }

    public function testAOneYearPlanStartsNow(): void
    {
        $page = $this->makePage($this->owner);
        $this->pay($page, 'basic');
        $page = $page->fresh();
        $this->assertSame('2027-10-13 12:00:00', $page->expires_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-13 12:00:00', $page->published_at->format('Y-m-d H:i:s'));
    }

    public function testRenewingEarlyKeepsTheDaysLeft(): void
    {
        $page = $this->makePage($this->owner, ['plan' => 'basic', 'expires_at' => '2026-12-01 00:00:00', 'published_at' => '2025-12-01 00:00:00']);
        $this->pay($page, 'basic');
        $page = $page->fresh();
        $this->assertSame('2027-12-01 00:00:00', $page->expires_at->format('Y-m-d H:i:s'));
        $this->assertSame('2025-12-01 00:00:00', $page->published_at->format('Y-m-d H:i:s'), 'first publication date kept');
    }

    public function testRenewingAfterExpiryStartsAgainFromToday(): void
    {
        $page = $this->makePage($this->owner, ['plan' => 'basic', 'expires_at' => '2026-01-01 00:00:00']);
        $this->pay($page, 'basic');
        $this->assertSame('2027-10-13 12:00:00', $page->fresh()->expires_at->format('Y-m-d H:i:s'));
    }

    public function testForeverClearsTheExpiryAndAllowsASubdomain(): void
    {
        $page = $this->makePage($this->owner, ['plan' => 'plus', 'expires_at' => '2027-01-01 00:00:00']);
        $this->assertFalse($page->allowsSubdomain());
        $this->pay($page, 'forever');
        $page = $page->fresh();
        $this->assertSame('forever', $page->plan);
        $this->assertNull($page->expires_at);
        $this->assertTrue($page->allowsSubdomain());
        $this->assertSame(Page::ACTIVE, $page->status(new \DateTimeImmutable('2099-01-01')));
    }

    public function testAnOrderIsActivatedExactlyOnce(): void
    {
        $page = $this->makePage($this->owner);
        $order = $this->pay($page, 'basic');
        $expires = $page->fresh()->expires_at->format('c');

        $this->assertFalse($this->billing->markPaid($order, 'manual', 'again', $this->now->modify('+1 day')));
        $this->assertFalse($this->billing->markPaid($order->fresh(), 'fake', null));
        $this->assertSame($expires, $page->fresh()->expires_at->format('c'), 'a second confirmation adds nothing');
        $this->assertNull($order->fresh()->provider_ref);
    }

    public function testUpgradingCancelsPendingOrdersForLowerPlans(): void
    {
        $page = $this->makePage($this->owner);
        // a Basic order left pending while the page was upgraded to Plus, then "paid" late
        $late = $this->billing->createOrder($this->owner->id, $page, 'basic', 'manual');
        $renewal = $this->billing->createOrder($this->owner->id, $page, 'plus', 'manual');
        $this->pay($page, 'plus');

        $this->assertSame(Order::CANCELLED, $late->fresh()->status, 'a cheaper plan can no longer be bought');
        $this->assertSame(Order::PENDING, $renewal->fresh()->status, 'renewing the same plan still can');
        $this->assertFalse($this->billing->markPaid($late->fresh(), 'manual', null, $this->now));
        $page = $page->fresh();
        $this->assertSame('plus', $page->plan);
        $this->assertSame('2027-10-13 12:00:00', $page->expires_at->format('Y-m-d H:i:s'), 'the ₱99 order adds nothing to Plus');
    }

    public function testForeverCancelsEveryOtherPendingOrder(): void
    {
        $page = $this->makePage($this->owner);
        $basic = $this->billing->createOrder($this->owner->id, $page, 'basic', 'manual');
        $plus = $this->billing->createOrder($this->owner->id, $page, 'plus', 'manual');
        $this->pay($page, 'forever');

        $this->assertSame(Order::CANCELLED, $basic->fresh()->status);
        $this->assertSame(Order::CANCELLED, $plus->fresh()->status);
        $this->assertFalse($this->billing->markPaid($basic->fresh(), 'manual', null, $this->now));
        $this->assertNull($page->fresh()->expires_at);
    }

    public function testActivatingALowerPlanDirectlyNeverDowngrades(): void
    {
        // defensive path: activate() is only reached for pending orders, but must never lower a plan
        $page = $this->makePage($this->owner, ['plan' => 'forever', 'expires_at' => null]);
        $this->billing->activate($page, 'basic', $this->now);
        $this->assertSame('forever', $page->fresh()->plan);
        $this->assertNull($page->fresh()->expires_at);
    }

    public function testAnOrderForADeletedPageIsMarkedPaidWithoutError(): void
    {
        $page = $this->makePage($this->owner);
        $order = $this->billing->createOrder($this->owner->id, $page, 'basic', 'manual');
        $this->db()->table('orders')->where('id', '=', $order->id)->update(['page_id' => null]);
        $this->assertTrue($this->billing->markPaid($order->fresh(), 'manual', null, $this->now));
    }
}

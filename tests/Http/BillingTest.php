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

    private function pay(Page $page, string $plan, string $period, ?\DateTimeImmutable $at = null): Order
    {
        $order = $this->billing->createOrder($this->owner->id, $page, $plan, $period, 'manual');
        $this->assertTrue($this->billing->markPaid($order, 'manual', null, $at ?? $this->now));
        return $order;
    }

    public function testAMonthlyPlanStartsNow(): void
    {
        $page = $this->makePage($this->owner);
        $order = $this->pay($page, 'sweet', 'month');
        $page = $page->fresh();
        $this->assertSame('sweet', $page->plan);
        $this->assertSame('2026-11-13 12:00:00', $page->expires_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-13 12:00:00', $page->published_at->format('Y-m-d H:i:s'));
        $this->assertSame(['month', 20000], [$order->fresh()->period, $order->fresh()->amount]);
    }

    public function testAYearlyPlanStartsNow(): void
    {
        $page = $this->makePage($this->owner);
        $order = $this->pay($page, 'sweetheart', 'year');
        $this->assertSame('2027-10-13 12:00:00', $page->fresh()->expires_at->format('Y-m-d H:i:s'));
        $this->assertSame(['year', 420000], [$order->fresh()->period, $order->fresh()->amount]);
    }

    public function testRenewingEarlyKeepsTheTimeLeft(): void
    {
        $page = $this->makePage($this->owner, ['plan' => 'sweet', 'expires_at' => '2026-12-01 00:00:00', 'published_at' => '2025-12-01 00:00:00']);
        $this->pay($page, 'sweet', 'month');
        $page = $page->fresh();
        $this->assertSame('2027-01-01 00:00:00', $page->expires_at->format('Y-m-d H:i:s'));
        $this->assertSame('2025-12-01 00:00:00', $page->published_at->format('Y-m-d H:i:s'), 'first publication date kept');
        $this->pay($page, 'sweet', 'year');
        $this->assertSame('2028-01-01 00:00:00', $page->fresh()->expires_at->format('Y-m-d H:i:s'), 'a monthly plan can be renewed for a year');
    }

    public function testAMonthEndsOnTheLastDayWhenTheDayIsMissing(): void
    {
        $page = $this->makePage($this->owner, ['plan' => 'sweet', 'expires_at' => '2027-01-31 18:00:00', 'published_at' => '2026-10-01 00:00:00']);
        $this->pay($page, 'sweet', 'month');
        $this->assertSame('2027-02-28 18:00:00', $page->fresh()->expires_at->format('Y-m-d H:i:s'), 'not March 3');
    }

    public function testRenewingAfterExpiryStartsAgainFromToday(): void
    {
        $page = $this->makePage($this->owner, ['plan' => 'sweet', 'expires_at' => '2026-01-01 00:00:00']);
        $this->pay($page, 'sweet', 'year');
        $this->assertSame('2027-10-13 12:00:00', $page->fresh()->expires_at->format('Y-m-d H:i:s'));
    }

    public function testUpgradingKeepsTheTimeLeftAndOpensTheAddress(): void
    {
        $page = $this->makePage($this->owner, ['plan' => 'sweet', 'expires_at' => '2026-12-01 00:00:00', 'published_at' => '2026-10-01 00:00:00']);
        $this->assertFalse($page->allowsSubdomain());
        $this->pay($page, 'sweetheart', 'month');
        $page = $page->fresh();
        $this->assertSame('sweetheart', $page->plan);
        $this->assertSame('2027-01-01 00:00:00', $page->expires_at->format('Y-m-d H:i:s'));
        $this->assertTrue($page->allowsSubdomain(), 'Sweetheart includes an address of its own');
    }

    public function testForeverClearsTheExpiry(): void
    {
        $page = $this->makePage($this->owner, ['plan' => 'sweetheart', 'expires_at' => '2027-01-01 00:00:00']);
        $order = $this->pay($page, 'forever', 'once');
        $page = $page->fresh();
        $this->assertSame('forever', $page->plan);
        $this->assertNull($page->expires_at);
        $this->assertTrue($page->allowsSubdomain());
        $this->assertSame(Page::ACTIVE, $page->status(new \DateTimeImmutable('2099-01-01')));
        $this->assertSame(['once', 499900], [$order->fresh()->period, $order->fresh()->amount]);
    }

    public function testAPlanIsOnlySoldForItsOwnPeriods(): void
    {
        $page = $this->makePage($this->owner);
        foreach ([['sweet', 'once'], ['forever', 'month'], ['forever', 'year'], ['sweetheart', 'week'], ['free', 'month']] as [$plan, $period]) {
            try {
                $this->billing->createOrder($this->owner->id, $page, $plan, $period, 'manual');
                $this->fail("{$plan} {$period} was sold");
            } catch (\DomainException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, Order::query()->count());
    }

    public function testAPhotoPackIsPaidOnceAndTouchesNoPage(): void
    {
        $page = $this->makePage($this->owner);
        $plan = $this->billing->createOrder($this->owner->id, $page, 'sweet', 'month', 'manual');
        $pack = $this->billing->createPhotoOrder($this->owner->id, $page, 3, 'manual');
        $this->assertSame([36, 30000], [$pack->photos, $pack->amount]);

        $this->assertTrue($this->billing->markPaid($pack, 'manual', 'gcash-1', $this->now));
        $this->assertFalse($this->billing->markPaid($pack->fresh(), 'manual', 'gcash-2', $this->now), 'paid once');
        $this->assertSame('free', $page->fresh()->plan);
        $this->assertNull($page->fresh()->expires_at);

        $pending = $this->billing->createPhotoOrder($this->owner->id, $page, 1, 'manual');
        $this->billing->markPaid($plan, 'manual', null, $this->now);
        $this->assertSame(Order::PENDING, $pending->fresh()->status, 'a new plan never cancels a pack waiting to be paid');
    }

    public function testAPhotoPackOrderHasOneToTenPacks(): void
    {
        foreach ([0, 11, -2] as $packs) {
            try {
                $this->billing->createPhotoOrder($this->owner->id, null, $packs, 'manual');
                $this->fail("{$packs} packs were sold");
            } catch (\DomainException $e) {
                $this->assertSame('Choose between 1 and 10 packs of photos.', $e->getMessage());
            }
        }
        $this->assertSame(120, $this->billing->createPhotoOrder($this->owner->id, null, 10, 'manual')->photos);
    }

    public function testAnOrderIsActivatedExactlyOnce(): void
    {
        $page = $this->makePage($this->owner);
        $order = $this->pay($page, 'sweet', 'month');
        $expires = $page->fresh()->expires_at->format('c');

        $this->assertFalse($this->billing->markPaid($order, 'manual', 'again', $this->now->modify('+1 day')));
        $this->assertFalse($this->billing->markPaid($order->fresh(), 'fake', null));
        $this->assertSame($expires, $page->fresh()->expires_at->format('c'), 'a second confirmation adds nothing');
        $this->assertNull($order->fresh()->provider_ref);
    }

    public function testUpgradingCancelsPendingOrdersForLowerPlans(): void
    {
        $page = $this->makePage($this->owner);
        // a Sweet order left pending while the page went to Sweetheart, then "paid" late
        $late = $this->billing->createOrder($this->owner->id, $page, 'sweet', 'month', 'manual');
        $renewal = $this->billing->createOrder($this->owner->id, $page, 'sweetheart', 'year', 'manual');
        $this->pay($page, 'sweetheart', 'month');

        $this->assertSame(Order::CANCELLED, $late->fresh()->status, 'a cheaper plan can no longer be bought');
        $this->assertSame(Order::PENDING, $renewal->fresh()->status, 'renewing the same plan, for any period, still can');
        $this->assertFalse($this->billing->markPaid($late->fresh(), 'manual', null, $this->now));
        $page = $page->fresh();
        $this->assertSame('sweetheart', $page->plan);
        $this->assertSame('2026-11-13 12:00:00', $page->expires_at->format('Y-m-d H:i:s'), 'the Sweet order adds nothing to Sweetheart');
    }

    public function testForeverCancelsEveryOtherPendingOrder(): void
    {
        $page = $this->makePage($this->owner);
        $sweet = $this->billing->createOrder($this->owner->id, $page, 'sweet', 'year', 'manual');
        $sweetheart = $this->billing->createOrder($this->owner->id, $page, 'sweetheart', 'month', 'manual');
        $this->pay($page, 'forever', 'once');

        $this->assertSame(Order::CANCELLED, $sweet->fresh()->status);
        $this->assertSame(Order::CANCELLED, $sweetheart->fresh()->status);
        $this->assertFalse($this->billing->markPaid($sweet->fresh(), 'manual', null, $this->now));
        $this->assertNull($page->fresh()->expires_at);
    }

    public function testActivatingALowerPlanDirectlyNeverDowngrades(): void
    {
        // defensive path: activate() is only reached for pending orders, but must never lower a plan
        $page = $this->makePage($this->owner, ['plan' => 'forever', 'expires_at' => null]);
        $this->billing->activate($page, 'sweet', 'month', $this->now);
        $this->assertSame('forever', $page->fresh()->plan);
        $this->assertNull($page->fresh()->expires_at);

        $page = $this->makePage($this->owner, ['plan' => 'sweetheart', 'expires_at' => '2026-12-01 00:00:00']);
        $this->billing->activate($page, 'sweet', 'month', $this->now);
        $this->assertSame('sweetheart', $page->fresh()->plan, 'a late Sweet payment keeps Sweetheart');
        $this->assertSame('2027-01-01 00:00:00', $page->fresh()->expires_at->format('Y-m-d H:i:s'), 'and only adds its month');
    }

    public function testAnOrderForADeletedPageIsMarkedPaidWithoutError(): void
    {
        $page = $this->makePage($this->owner);
        $order = $this->billing->createOrder($this->owner->id, $page, 'sweet', 'month', 'manual');
        $this->db()->table('orders')->where('id', '=', $order->id)->update(['page_id' => null]);
        $this->assertTrue($this->billing->markPaid($order->fresh(), 'manual', null, $this->now));
    }
}

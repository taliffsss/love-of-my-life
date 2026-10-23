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

    public function testTheQaAccountPaysAtMostTheTestingAmountAndNobodyElseDoes(): void
    {
        $this->app->make(\Naluz\Config\Repository::class)->set('saas.qa.email', ' Owner@Example.com ');
        $page = $this->makePage($this->owner);

        $plan = $this->billing->createOrder($this->owner->id, $page, 'sweetheart', 'year', 'paymongo');
        $this->assertSame(['sweetheart', 'year', 100], [$plan->plan, $plan->period, $plan->amount], 'the plan is the real one, only the money changes');
        $photos = $this->billing->createPhotoOrder($this->owner->id, $page, 3, 'paymongo');
        $this->assertSame(100, $photos->amount);
        $this->assertGreaterThan(0, $photos->photos, 'the photos bought are unchanged');

        $other = $this->makeUser('someone@example.com');
        $this->assertSame(35000, $this->billing->createOrder($other->id, $this->makePage($other), 'sweetheart', 'month', 'paymongo')->amount, 'everyone else pays the price');
        $this->assertSame(3 * $this->app->make(\App\Services\PhotoAllowance::class)->packPrice(), $this->billing->createPhotoOrder($other->id, null, 3, 'paymongo')->amount);
    }

    public function testWithoutAQaEmailEveryonePaysThePrice(): void
    {
        $this->app->make(\Naluz\Config\Repository::class)->set('saas.qa.email', '');
        $order = $this->billing->createOrder($this->owner->id, $this->makePage($this->owner), 'sweet', 'month', 'paymongo');
        $this->assertSame(20000, $order->amount);
    }

    /** @return array<string,array{string,string,string}> paid at, period, expires */
    public static function validityCases(): array
    {
        return [
            'a month from an ordinary day' => ['2026-10-23 05:21:01', 'month', '2026-11-23 05:21:01'],
            'a month across the year end' => ['2026-12-31 23:59:59', 'month', '2027-01-31 23:59:59'],
            'Oct 31 + 1 month is Nov 30' => ['2026-10-31 10:00:00', 'month', '2026-11-30 10:00:00'],
            'Jan 31 + 1 month, ordinary year' => ['2026-01-31 08:00:00', 'month', '2026-02-28 08:00:00'],
            'Jan 31 + 1 month, leap year' => ['2028-01-31 08:00:00', 'month', '2028-02-29 08:00:00'],
            'a year from an ordinary day' => ['2026-10-23 05:21:01', 'year', '2027-10-23 05:21:01'],
            'Feb 29 + 1 year is Feb 28' => ['2028-02-29 12:00:00', 'year', '2029-02-28 12:00:00'],
            'a year paid on the last second of a year' => ['2026-12-31 23:59:59', 'year', '2027-12-31 23:59:59'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('validityCases')]
    public function testAOneTimePaymentIsValidFromTheMomentItIsPaidForExactlyItsPeriod(string $paidAt, string $period, string $expires): void
    {
        $paid = new \DateTimeImmutable($paidAt, new \DateTimeZone('UTC'));
        $page = $this->makePage($this->owner);
        $order = $this->billing->createOrder($this->owner->id, $page, 'sweet', $period, 'paymongo');
        $this->assertTrue($this->billing->markPaid($order, 'paymongo', 'pi_x', $paid));
        $page = $page->fresh();

        $this->assertSame($expires, $page->expires_at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), 'valid for the period, counted from the payment');
        $this->assertSame($paidAt, $order->fresh()->paid_at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
        $this->assertTrue($page->isLive($paid), 'live the moment it is paid, never already expired');
        $this->assertTrue($page->isLive($page->expires_at->modify('-1 second')), 'live until the last second');
        $this->assertFalse($page->isLive($page->expires_at), 'expired exactly at the expiry');
        $this->assertSame($paidAt, $page->published_at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
    }

    public function testAnExpiredPageThatIsPaidAgainIsValidFromTheNewPaymentNotFromTheOldExpiry(): void
    {
        $page = $this->makePage($this->owner);
        $this->pay($page, 'sweet', 'month', new \DateTimeImmutable('2026-01-10 00:00:00', new \DateTimeZone('UTC')));
        $this->assertFalse($page->fresh()->isLive(new \DateTimeImmutable('2026-03-01 00:00:00', new \DateTimeZone('UTC'))), 'lapsed in February');

        $again = new \DateTimeImmutable('2026-03-01 09:30:00', new \DateTimeZone('UTC'));
        $this->pay($page, 'sweet', 'month', $again);
        $page = $page->fresh();
        $this->assertSame('2026-04-01 09:30:00', $page->expires_at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
        $this->assertTrue($page->isLive($again));
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

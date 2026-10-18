<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Models\Order;
use App\Models\Page;
use App\Models\User;
use App\Services\Billing;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\PhotoAllowance;
use Naluz\Tests\TestCase;

final class OrdersTest extends TestCase
{
    private User $owner;
    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->makeUser();
        $this->signIn($this->owner);
        $this->page = $this->makePage($this->owner);
    }

    private function useFakeCheckout(): void
    {
        $this->app->make(\Naluz\Config\Repository::class)->set('saas.payments.driver', 'fake');
    }

    private function buy(string $plan, string $period): Order
    {
        $response = $this->submit('POST', '/dashboard/pages/' . $this->page->slug . '/orders', ['plan' => $plan, 'period' => $period]);
        $order = Order::query()->orderBy('id', 'desc')->first();
        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame('/dashboard/orders/' . $order->reference, $response->getHeaderLine('Location'));
        return $order;
    }

    public function testWithoutOnlineCheckoutTheOrderShowsHowToPay(): void
    {
        $order = $this->buy('sweetheart', 'month');

        $this->assertSame('sweetheart', $order->plan);
        $this->assertSame('month', $order->period);
        $this->assertSame(35000, $order->amount);
        $this->assertSame('PHP', $order->currency);
        $this->assertSame(Order::PENDING, $order->status);
        $this->assertSame('manual', $order->provider);
        $this->assertMatchesRegularExpression('/^LM-[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}$/', $order->reference);

        $html = (string) $this->get('/dashboard/orders/' . $order->reference)->getBody();
        $this->assertStringContainsString('How to pay', $html);
        $this->assertStringContainsString('₱350', $html);
        $this->assertStringContainsString('Sweetheart, one month', $html);
        $this->assertStringNotContainsString('(test)', $html);
        $this->assertSame(404, $this->submit('POST', '/dashboard/orders/' . $order->reference . '/fake-pay')->getStatusCode(), 'no test payments');
        $this->assertSame(Page::DRAFT, $this->page->fresh()->status());
    }

    public function testBuyingTwiceReusesThePendingOrder(): void
    {
        $first = $this->buy('sweet', 'month');
        $second = $this->buy('sweet', 'month');
        $this->assertSame($first->id, $second->id);
        $yearly = $this->buy('sweet', 'year');
        $this->assertNotSame($first->id, $yearly->id, 'another period is another order');
        $this->assertSame(240000, $yearly->amount, 'twelve months of ₱200');
        $this->assertSame(2, Order::query()->count());
    }

    private function buyPhotos(array $data): Order
    {
        $response = $this->submit('POST', '/dashboard/photo-packs', $data);
        $order = Order::query()->orderBy('id', 'desc')->first();
        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame('/dashboard/orders/' . $order->reference, $response->getHeaderLine('Location'));
        return $order;
    }

    public function testPhotoPacksAreSoldInTwelvesAtOneHundredPesos(): void
    {
        $order = $this->buyPhotos(['packs' => '2', 'page' => $this->page->slug]);
        $this->assertSame([Order::PHOTO_PACK, 'once', 24, 20000, $this->page->id, Order::PENDING], [$order->plan, $order->period, $order->photos, $order->amount, $order->page_id, $order->status]);
        $this->assertSame($order->id, $this->buyPhotos(['packs' => '2', 'page' => $this->page->slug])->id, 'clicking twice reuses the pending order');
        $this->assertNotSame($order->id, $this->buyPhotos(['packs' => '1'])->id, 'from the dashboard: no page');

        $html = (string) $this->get('/dashboard/orders/' . $order->reference)->getBody();
        $this->assertStringContainsString('24 more photos for your account', $html);
        $this->assertStringContainsString('₱200', $html);
        $this->assertStringContainsString('How to pay', $html);
        $this->assertStringContainsString('#photos">Back to the page', $html);
        $this->assertSame(6, $this->app->make(PhotoAllowance::class)->of($this->owner->id)['total'], 'unpaid packs add nothing');
    }

    public function testThePackCountIsCheckedOnTheServer(): void
    {
        foreach (['0', '11', '-1', 'two', '1.5', ''] as $packs) {
            $response = $this->submit('POST', '/dashboard/photo-packs', ['packs' => $packs]);
            $this->assertSame(303, $response->getStatusCode(), "packs={$packs}");
            $this->assertStringNotContainsString('/dashboard/orders/', $response->getHeaderLine('Location'), "packs={$packs} is refused");
        }
        $theirs = $this->makePage($this->makeUser('other@example.com'));
        $this->assertSame(404, $this->submit('POST', '/dashboard/photo-packs', ['packs' => '1', 'page' => $theirs->slug])->getStatusCode(), 'not from someone else\'s page');
        $this->assertSame(0, Order::query()->count());
    }

    public function testAPaidPackAddsItsPhotosToTheAccount(): void
    {
        $this->useFakeCheckout();
        $order = $this->buyPhotos(['packs' => '1', 'page' => $this->page->slug]);
        $response = $this->submit('POST', '/dashboard/orders/' . $order->reference . '/fake-pay');

        $this->assertSame('/dashboard/pages/' . $this->page->slug . '#photos', $response->getHeaderLine('Location'));
        $this->assertSame(Order::PAID, $order->fresh()->status);
        $usage = $this->app->make(PhotoAllowance::class)->of($this->owner->id);
        $this->assertSame([6, 12, 18], [$usage['included'], $usage['bought'], $usage['total']]);
        $this->assertSame('free', $this->page->fresh()->plan, 'a pack never changes a plan');
        $this->assertStringContainsString('12 more photos are ready to use', (string) $this->get('/dashboard')->getBody());
    }

    public function testPhotoPacksStayWithTheAccountWhenTheirPageIsDeleted(): void
    {
        $this->useFakeCheckout();
        $order = $this->buyPhotos(['packs' => '1', 'page' => $this->page->slug]);
        $this->submit('POST', '/dashboard/orders/' . $order->reference . '/fake-pay');
        $this->submit('DELETE', '/dashboard/pages/' . $this->page->slug, ['confirm' => 'delete']);

        $this->assertNull($order->fresh()->page_id);
        $this->assertSame(18, $this->app->make(PhotoAllowance::class)->of($this->owner->id)['total']);
    }

    public function testATestPaymentPublishesThePageForAMonth(): void
    {
        $this->useFakeCheckout();
        $order = $this->buy('sweet', 'month');
        $this->assertStringContainsString('(test)', (string) $this->get('/dashboard/orders/' . $order->reference)->getBody());

        $response = $this->submit('POST', '/dashboard/orders/' . $order->reference . '/fake-pay');
        $this->assertSame('/dashboard/pages/' . $this->page->slug . '#plan', $response->getHeaderLine('Location'));

        $page = $this->page->fresh();
        $this->assertSame('sweet', $page->plan);
        $this->assertSame(Page::ACTIVE, $page->status());
        $this->assertNotNull($page->published_at);
        $days = (int) round(($page->expires_at->getTimestamp() - time()) / 86400);
        $this->assertContains($days, [28, 29, 30, 31], 'one calendar month');
        $this->assertSame(Order::PAID, $order->fresh()->status);
        $this->assertStringStartsWith('fake_', (string) $order->fresh()->provider_ref);
    }

    public function testOnlyUpgradesAndRenewalsCanBeBought(): void
    {
        $this->page->forceFill(['plan' => 'sweetheart', 'expires_at' => '2099-01-01 00:00:00'])->save();
        $url = '/dashboard/pages/' . $this->page->slug . '/orders';
        foreach ([['sweet', 'month'], ['free', 'month'], ['platinum', 'month'], ['sweetheart', 'once'], ['forever', 'month'], ['sweetheart', '']] as [$plan, $period]) {
            $this->submit('POST', $url, ['plan' => $plan, 'period' => $period]);
        }
        $this->assertSame(0, Order::query()->count(), 'no downgrade, no free order, no unknown plan, no period it is not sold for');

        $this->buy('sweetheart', 'year');   // renewal, for a year this time
        $this->buy('forever', 'once');
        $this->assertSame(2, Order::query()->count());

        $this->page->forceFill(['plan' => 'forever', 'expires_at' => null])->save();
        $this->submit('POST', $url, ['plan' => 'forever', 'period' => 'once']);
        $this->assertSame(2, Order::query()->count(), 'nothing left to buy on Forever');
    }

    public function testThePlanMustIncludeThePagesTemplate(): void
    {
        $this->page->forceFill(['layout' => 'storybook'])->save();
        $url = '/dashboard/pages/' . $this->page->slug . '/orders';
        $this->submit('POST', $url, ['plan' => 'sweet', 'period' => 'month']);
        $this->assertSame(0, Order::query()->count(), 'Sweet does not include the Storybook');
        $html = (string) $this->get('/dashboard/pages/' . $this->page->slug)->getBody();
        $this->assertStringContainsString('Your page uses the Storybook template, which comes with Sweetheart and Forever.', $html);

        $this->buy('sweetheart', 'month');
        $this->page->forceFill(['layout' => 'envelope'])->save();
        $this->buy('sweet', 'year');
        $this->assertSame(2, Order::query()->count());
    }

    public function testAnotherOwnersOrderIsInvisible(): void
    {
        $other = $this->makeUser('other@example.com');
        $theirPage = $this->makePage($other);
        $order = $this->app->make(Billing::class)->createOrder($other->id, $theirPage, 'sweet', 'month', 'fake');
        $this->useFakeCheckout();

        $this->assertSame(404, $this->get('/dashboard/orders/' . $order->reference)->getStatusCode());
        $this->assertSame(404, $this->submit('POST', '/dashboard/orders/' . $order->reference . '/fake-pay')->getStatusCode());
        $this->assertSame(Order::PENDING, $order->fresh()->status);
    }

    /** @return array<string,array{string}> */
    public static function liveEnvironments(): array
    {
        return ['production' => ['production'], 'misspelt' => ['prod'], 'capitalised' => ['Production'], 'empty' => ['']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('liveEnvironments')]
    public function testTheFakeGatewayOnlyRunsInTestEnvironments(string $environment): void
    {
        $this->expectException(\LogicException::class);
        new FakeGateway($environment);
    }

    public function testTheFakeGatewayRunsLocally(): void
    {
        $this->assertSame('fake', (new FakeGateway('local'))->name());
        $this->assertSame('fake', (new FakeGateway('staging'))->name());
    }

    public function testTheGatewayFollowsTheConfiguration(): void
    {
        $this->assertSame('manual', $this->app->make(PaymentGateway::class)->name());
        $this->useFakeCheckout();
        $this->assertSame('fake', $this->app->make(PaymentGateway::class)->name());
    }
}

<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Models\Order;
use App\Models\Page;
use App\Models\User;
use App\Services\Billing;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGateway;
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

    private function buy(string $plan): Order
    {
        $response = $this->submit('POST', '/dashboard/pages/' . $this->page->slug . '/orders', ['plan' => $plan]);
        $order = Order::query()->orderBy('id', 'desc')->first();
        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame('/dashboard/orders/' . $order->reference, $response->getHeaderLine('Location'));
        return $order;
    }

    public function testWithoutOnlineCheckoutTheOrderShowsHowToPay(): void
    {
        $order = $this->buy('plus');

        $this->assertSame('plus', $order->plan);
        $this->assertSame(19900, $order->amount);
        $this->assertSame('PHP', $order->currency);
        $this->assertSame(Order::PENDING, $order->status);
        $this->assertSame('manual', $order->provider);
        $this->assertMatchesRegularExpression('/^LM-[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}$/', $order->reference);

        $html = (string) $this->get('/dashboard/orders/' . $order->reference)->getBody();
        $this->assertStringContainsString('How to pay', $html);
        $this->assertStringContainsString('₱199', $html);
        $this->assertStringNotContainsString('(test)', $html);
        $this->assertSame(404, $this->submit('POST', '/dashboard/orders/' . $order->reference . '/fake-pay')->getStatusCode(), 'no test payments');
        $this->assertSame(Page::DRAFT, $this->page->fresh()->status());
    }

    public function testBuyingTwiceReusesThePendingOrder(): void
    {
        $first = $this->buy('basic');
        $second = $this->buy('basic');
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Order::query()->count());
    }

    public function testATestPaymentPublishesThePageForAYear(): void
    {
        $this->useFakeCheckout();
        $order = $this->buy('basic');
        $this->assertStringContainsString('(test)', (string) $this->get('/dashboard/orders/' . $order->reference)->getBody());

        $response = $this->submit('POST', '/dashboard/orders/' . $order->reference . '/fake-pay');
        $this->assertSame('/dashboard/pages/' . $this->page->slug . '#plan', $response->getHeaderLine('Location'));

        $page = $this->page->fresh();
        $this->assertSame('basic', $page->plan);
        $this->assertSame(Page::ACTIVE, $page->status());
        $this->assertNotNull($page->published_at);
        $days = (int) round(($page->expires_at->getTimestamp() - time()) / 86400);
        $this->assertSame(365, $days);
        $this->assertSame(Order::PAID, $order->fresh()->status);
        $this->assertStringStartsWith('fake_', (string) $order->fresh()->provider_ref);
    }

    public function testOnlyUpgradesAndRenewalsCanBeBought(): void
    {
        $this->page->forceFill(['plan' => 'plus', 'expires_at' => '2099-01-01 00:00:00'])->save();
        $this->submit('POST', '/dashboard/pages/' . $this->page->slug . '/orders', ['plan' => 'basic']);
        $this->submit('POST', '/dashboard/pages/' . $this->page->slug . '/orders', ['plan' => 'free']);
        $this->submit('POST', '/dashboard/pages/' . $this->page->slug . '/orders', ['plan' => 'platinum']);
        $this->assertSame(0, Order::query()->count(), 'no downgrade, no free order, no unknown plan');

        $this->buy('plus');   // renewal
        $this->buy('forever');
        $this->assertSame(2, Order::query()->count());

        $this->page->forceFill(['plan' => 'forever', 'expires_at' => null])->save();
        $this->submit('POST', '/dashboard/pages/' . $this->page->slug . '/orders', ['plan' => 'forever']);
        $this->assertSame(2, Order::query()->count(), 'nothing left to buy on Forever');
    }

    public function testAnotherOwnersOrderIsInvisible(): void
    {
        $other = $this->makeUser('other@example.com');
        $theirPage = $this->makePage($other);
        $order = $this->app->make(Billing::class)->createOrder($other->id, $theirPage, 'basic', 'fake');
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

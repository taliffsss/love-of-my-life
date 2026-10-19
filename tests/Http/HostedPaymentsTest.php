<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Models\Order;
use App\Models\Page;
use App\Models\User;
use App\Services\Payments\PayMongoGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\XenditGateway;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use Naluz\Tests\TestCase;
use Psr\Http\Message\ResponseInterface;

final class HostedPaymentsTest extends TestCase
{
    private const WEBHOOK_SECRET = 'whsec_test';
    private const XENDIT_TOKEN = 'xendit-token';

    private User $owner;
    private Page $page;
    /** @var list<array{request:\Psr\Http\Message\RequestInterface}> */
    private array $history = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->makeUser();
        $this->signIn($this->owner);
        $this->page = $this->makePage($this->owner);
    }

    /** @param list<GuzzleResponse> $answers */
    private function client(array $answers): Client
    {
        $stack = HandlerStack::create(new MockHandler($answers));
        $stack->push(Middleware::history($this->history));
        return new Client(['handler' => $stack, 'http_errors' => false]);
    }

    private function usePayMongo(array $answers = [], string $key = 'sk_test_abc'): void
    {
        $this->app->make(\Naluz\Config\Repository::class)->set('saas.payments.driver', 'paymongo');
        $this->app->instance(PayMongoGateway::class, new PayMongoGateway($key, self::WEBHOOK_SECRET, ['gcash', 'card'], 'https://love.test', $this->client($answers)));
    }

    private function useXendit(array $answers = []): void
    {
        $this->app->make(\Naluz\Config\Repository::class)->set('saas.payments.driver', 'xendit');
        $this->app->instance(XenditGateway::class, new XenditGateway('xnd_development_abc', self::XENDIT_TOKEN, 'https://love.test', $this->client($answers)));
    }

    private function answer(array $data, int $status = 200): GuzzleResponse
    {
        return new GuzzleResponse($status, ['Content-Type' => 'application/json'], json_encode($data));
    }

    private function buy(): ResponseInterface
    {
        return $this->submit('POST', '/dashboard/pages/' . $this->page->slug . '/orders', ['plan' => 'sweetheart', 'period' => 'month']);
    }

    private function order(): Order
    {
        $order = Order::query()->orderBy('id', 'desc')->first();
        $this->assertInstanceOf(Order::class, $order);
        return $order;
    }

    private function fresh(Order $order): Order
    {
        return Order::query()->withoutCache()->find($order->id);
    }

    private function checkoutAnswer(): GuzzleResponse
    {
        return $this->answer(['data' => ['id' => 'cs_123', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/cs_123']]]);
    }

    private function webhook(string $uri, array $payload, array $headers): ResponseInterface
    {
        $body = json_encode($payload);
        $request = (new ServerRequest('POST', $uri, $headers + ['Content-Type' => 'application/json']))
            ->withBody((new Psr17Factory())->createStream($body));
        return $this->send($request);
    }

    /** A signed PayMongo event, the way PayMongo sends it. */
    private function payMongoEvent(Order $order, array $over = [], bool $live = false, ?string $sign = null, string $type = 'checkout_session.payment.paid'): ResponseInterface
    {
        $payload = ['data' => ['id' => 'evt_1', 'type' => 'event', 'attributes' => [
            'type' => $type,
            'livemode' => $live,
            'data' => ['id' => $order->provider_ref, 'type' => 'checkout_session', 'attributes' => [
                'reference_number' => $order->reference,
                'payments' => [['attributes' => ['amount' => $order->amount, 'currency' => 'PHP', 'status' => 'paid']]],
            ]],
        ]]];
        $payload = array_replace_recursive($payload, $over);
        $body = json_encode($payload);
        $t = '1700000000';
        $signature = hash_hmac('sha256', $t . '.' . $body, $sign ?? self::WEBHOOK_SECRET);
        $header = $live ? "t={$t},te=,li={$signature}" : "t={$t},te={$signature},li=";
        $request = (new ServerRequest('POST', '/api/webhooks/paymongo', ['Paymongo-Signature' => $header, 'Content-Type' => 'application/json']))
            ->withBody((new Psr17Factory())->createStream($body));
        return $this->send($request);
    }

    public function testPayMongoCheckoutIsOpenedForTheOrderAndTheCustomerSentThere(): void
    {
        $this->usePayMongo([$this->checkoutAnswer()]);
        $response = $this->buy();

        $this->assertSame('https://checkout.paymongo.com/cs_123', $response->getHeaderLine('Location'));
        $order = $this->order();
        $this->assertSame('paymongo', $order->provider);
        $this->assertSame('cs_123', $order->provider_ref);
        $this->assertSame('https://checkout.paymongo.com/cs_123', $order->checkout_url);
        $this->assertSame(Order::PENDING, $order->status);

        $request = $this->history[0]['request'];
        $this->assertSame('https://api.paymongo.com/v1/checkout_sessions', (string) $request->getUri());
        $this->assertSame('Basic ' . base64_encode('sk_test_abc:'), $request->getHeaderLine('Authorization'));
        $attributes = json_decode((string) $request->getBody(), true)['data']['attributes'];
        $this->assertSame(35000, $attributes['line_items'][0]['amount']);
        $this->assertSame('PHP', $attributes['line_items'][0]['currency']);
        $this->assertSame($order->reference, $attributes['reference_number']);
        $this->assertSame(['gcash', 'card'], $attributes['payment_method_types']);
        $this->assertSame('https://love.test/dashboard/orders/' . $order->reference, $attributes['success_url']);
    }

    public function testBuyingTwiceReturnsToTheSameCheckout(): void
    {
        $this->usePayMongo([$this->checkoutAnswer()]);
        $first = $this->buy()->getHeaderLine('Location');
        $second = $this->buy()->getHeaderLine('Location');

        $this->assertSame($first, $second);
        $this->assertCount(1, $this->history, 'one checkout opened');
        $this->assertSame(1, Order::query()->count());
    }

    public function testWhenThePayMongoApiFailsTheCustomerHearsAndTheOrderStaysPending(): void
    {
        $this->usePayMongo([$this->answer(['errors' => []], 500)]);
        $response = $this->buy();

        $this->assertSame('/dashboard/pages/' . $this->page->slug . '#plan', $response->getHeaderLine('Location'));
        $order = $this->order();
        $this->assertSame(Order::PENDING, $order->status);
        $this->assertSame('', (string) $order->checkout_url);
        $this->assertStringContainsString('could not start the checkout', (string) $this->get('/dashboard')->getBody());
    }

    public function testASignedPayMongoWebhookMarksTheOrderPaidAndActivatesThePage(): void
    {
        $this->usePayMongo([$this->checkoutAnswer()]);
        $this->buy();
        $order = $this->order();

        $this->assertSame(200, $this->payMongoEvent($order)->getStatusCode());
        $paid = $this->fresh($order);
        $this->assertSame(Order::PAID, $paid->status);
        $this->assertSame('cs_123', $paid->provider_ref);
        $page = Page::query()->withoutCache()->find($this->page->id);
        $this->assertSame('sweetheart', $page->plan);
        $this->assertTrue($page->isLive());

        // delivered again (providers retry): nothing changes
        $expires = $page->expires_at;
        $this->assertSame(200, $this->payMongoEvent($order)->getStatusCode());
        $this->assertEquals($expires, Page::query()->withoutCache()->find($this->page->id)->expires_at);
    }

    public function testPayMongoLiveEventsNeedTheLiveSignature(): void
    {
        $this->usePayMongo([$this->checkoutAnswer()]);
        $this->buy();
        $order = $this->order();

        // a live event carries te= empty; an attacker cannot pass it with a test-style signature
        $this->assertSame(400, $this->payMongoEvent($order, [], true, 'wrong-secret')->getStatusCode());
        $this->assertSame(Order::PENDING, $this->fresh($order)->status);
        $this->assertSame(200, $this->payMongoEvent($order, [], true)->getStatusCode());
        $this->assertSame(Order::PAID, $this->fresh($order)->status);
    }

    public function testPayMongoWebhooksWithoutAValidSignatureAreRefused(): void
    {
        $this->usePayMongo([$this->checkoutAnswer()]);
        $this->buy();
        $order = $this->order();

        $this->assertSame(400, $this->payMongoEvent($order, [], false, 'not-the-secret')->getStatusCode());
        $this->assertSame(400, $this->webhook('/api/webhooks/paymongo', ['data' => []], [])->getStatusCode());
        $this->assertSame(400, $this->webhook('/api/webhooks/paymongo', ['data' => []], ['Paymongo-Signature' => 't=1,te=abc,li='])->getStatusCode());
        $this->assertSame(Order::PENDING, $this->fresh($order)->status);
    }

    public function testAPaymentOfTheWrongAmountOrForTheWrongCheckoutIsNotCredited(): void
    {
        $this->usePayMongo([$this->checkoutAnswer()]);
        $this->buy();
        $order = $this->order();

        $short = ['data' => ['attributes' => ['data' => ['attributes' => ['payments' => [['attributes' => ['amount' => 100]]]]]]]];
        $this->assertSame(200, $this->payMongoEvent($order, $short)->getStatusCode());
        $other = ['data' => ['attributes' => ['data' => ['id' => 'cs_other']]]];
        $this->assertSame(200, $this->payMongoEvent($order, $other)->getStatusCode());
        $unknown = ['data' => ['attributes' => ['data' => ['attributes' => ['reference_number' => 'LM-AAAA-BBBB']]]]];
        $this->assertSame(200, $this->payMongoEvent($order, $unknown)->getStatusCode());
        $this->assertSame(200, $this->payMongoEvent($order, [], false, null, 'payment.failed')->getStatusCode());

        $this->assertSame(Order::PENDING, $this->fresh($order)->status);
        $this->assertSame('free', Page::query()->withoutCache()->find($this->page->id)->plan);
    }

    public function testXenditInvoiceIsOpenedInPesosWithTheBuyersEmail(): void
    {
        $this->useXendit([$this->answer(['id' => 'inv_9', 'invoice_url' => 'https://checkout.xendit.co/web/inv_9'])]);
        $response = $this->buy();

        $this->assertSame('https://checkout.xendit.co/web/inv_9', $response->getHeaderLine('Location'));
        $order = $this->order();
        $this->assertSame('xendit', $order->provider);
        $this->assertSame('inv_9', $order->provider_ref);

        $request = $this->history[0]['request'];
        $this->assertSame('https://api.xendit.co/v2/invoices', (string) $request->getUri());
        $this->assertSame('Basic ' . base64_encode('xnd_development_abc:'), $request->getHeaderLine('Authorization'));
        $body = json_decode((string) $request->getBody(), true);
        $this->assertEquals(350, $body['amount']);
        $this->assertSame($order->reference, $body['external_id']);
        $this->assertSame('owner@example.com', $body['payer_email']);
    }

    public function testXenditWebhookNeedsTheTokenAndCreditsOnce(): void
    {
        $this->useXendit([$this->answer(['id' => 'inv_9', 'invoice_url' => 'https://checkout.xendit.co/web/inv_9'])]);
        $this->buy();
        $order = $this->order();
        $paid = ['id' => 'inv_9', 'external_id' => $order->reference, 'status' => 'PAID', 'amount' => 350, 'paid_amount' => 350, 'currency' => 'PHP'];

        $this->assertSame(400, $this->webhook('/api/webhooks/xendit', $paid, ['x-callback-token' => 'wrong'])->getStatusCode());
        $this->assertSame(400, $this->webhook('/api/webhooks/xendit', $paid, [])->getStatusCode());
        $this->assertSame(Order::PENDING, $this->fresh($order)->status);

        $this->assertSame(200, $this->webhook('/api/webhooks/xendit', $paid, ['x-callback-token' => self::XENDIT_TOKEN])->getStatusCode());
        $this->assertSame(Order::PAID, $this->fresh($order)->status);
        $this->assertSame('sweetheart', Page::query()->withoutCache()->find($this->page->id)->plan);
        $this->assertSame(200, $this->webhook('/api/webhooks/xendit', $paid, ['x-callback-token' => self::XENDIT_TOKEN])->getStatusCode());
    }

    public function testXenditExpiredCancelsAndAWrongAmountIsNotCredited(): void
    {
        $this->useXendit([$this->answer(['id' => 'inv_9', 'invoice_url' => 'https://checkout.xendit.co/web/inv_9'])]);
        $this->buy();
        $order = $this->order();
        $token = ['x-callback-token' => self::XENDIT_TOKEN];

        $this->webhook('/api/webhooks/xendit', ['id' => 'inv_9', 'external_id' => $order->reference, 'status' => 'PAID', 'paid_amount' => 3.5, 'currency' => 'PHP'], $token);
        $this->assertSame(Order::PENDING, $this->fresh($order)->status);
        $this->webhook('/api/webhooks/xendit', ['id' => 'inv_9', 'external_id' => $order->reference, 'status' => 'PENDING'], $token);
        $this->assertSame(Order::PENDING, $this->fresh($order)->status);

        $this->webhook('/api/webhooks/xendit', ['id' => 'inv_9', 'external_id' => $order->reference, 'status' => 'EXPIRED'], $token);
        $this->assertSame(Order::CANCELLED, $this->fresh($order)->status);
    }

    public function testThePendingOrderPageOffersTheHostedCheckout(): void
    {
        $this->usePayMongo([$this->checkoutAnswer()]);
        $this->buy();
        $order = $this->order();
        $html = (string) $this->get('/dashboard/orders/' . $order->reference)->getBody();
        $this->assertStringContainsString('href="https://checkout.paymongo.com/cs_123"', $html);
        $this->assertStringNotContainsString('How to pay', $html);
    }

    public function testAHostedDriverWithoutItsKeyFailsLoudly(): void
    {
        $this->usePayMongo([], '');
        $this->expectException(\LogicException::class);
        $this->app->make(PaymentGateway::class);
    }

    public function testWebhooksAreOpenToTheProviderButNotToTheSiteForms(): void
    {
        // not configured: the PayMongo endpoint says so instead of accepting anything
        $this->app->instance(PayMongoGateway::class, new PayMongoGateway('', '', [], 'https://love.test'));
        $this->assertSame(404, $this->webhook('/api/webhooks/paymongo', [], ['Paymongo-Signature' => 't=1,te=a,li='])->getStatusCode());
    }
}

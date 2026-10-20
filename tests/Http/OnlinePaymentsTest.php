<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Models\Order;
use App\Models\Page;
use App\Models\PaymentAttempt;
use App\Models\ProviderRef;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\PayMongoGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Subscriptions;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Naluz\Config\Repository;
use Naluz\Tests\TestCase;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;

final class OnlinePaymentsTest extends TestCase
{
    private const WEBHOOK_SECRET = 'whsec_online';

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
    private function usePayMongo(array $answers = [], bool $subscriptions = false): void
    {
        $this->history = []; // the calls made through this gateway from now on
        $stack = HandlerStack::create(new MockHandler($answers));
        $stack->push(Middleware::history($this->history));
        $this->app->make(Repository::class)->set('saas.payments.driver', 'paymongo');
        $this->app->instance(PayMongoGateway::class, new PayMongoGateway(
            'sk_test_sec',
            self::WEBHOOK_SECRET,
            ['gcash'],
            'https://love.test',
            new Client(['handler' => $stack, 'http_errors' => false]),
            'pk_test_pub',
            $subscriptions,
        ));
    }

    private function answer(array $data, int $status = 200): GuzzleResponse
    {
        return new GuzzleResponse($status, ['Content-Type' => 'application/json'], json_encode($data));
    }

    /** What PayMongo answers to start one payment: the intent, the payment method and, for a wallet, the attach with its approval page. */
    private function walletAnswers(string $intent = 'pi_1'): array
    {
        return [
            $this->answer(['data' => ['id' => $intent, 'attributes' => ['client_key' => $intent . '_ck']]]),
            $this->answer(['data' => ['id' => 'pm_' . $intent]]),
            $this->answer(['data' => ['id' => $intent, 'attributes' => ['status' => 'awaiting_next_action', 'next_action' => ['type' => 'redirect', 'redirect' => ['url' => 'https://pay.example.com/approve/' . $intent]]]]]),
        ];
    }

    private function cardAnswers(string $intent = 'pi_c'): array
    {
        return [$this->answer(['data' => ['id' => $intent, 'attributes' => ['client_key' => $intent . '_ck']]])];
    }

    /** What PayMongo answers to start a subscription: customer, plan, subscription, the first invoice's intent (and for Maya the method and attach). */
    private function subscriptionAnswers(string $method, string $sub = 'sub_1', string $intent = 'pi_s1', bool $newCustomerAndPlan = true): array
    {
        $out = [];
        if ($newCustomerAndPlan) {
            $out[] = $this->answer(['data' => ['id' => 'cus_1']]);
            $out[] = $this->answer(['data' => ['id' => 'plan_1']]);
        }
        $out[] = $this->answer(['data' => ['id' => $sub, 'attributes' => ['status' => 'incomplete', 'latest_invoice' => ['id' => 'inv_1', 'status' => 'open', 'payment_intent' => ['id' => $intent, 'status' => 'awaiting_payment_method']]]]]);
        $out[] = $this->answer(['data' => ['id' => $intent, 'attributes' => ['client_key' => $intent . '_ck']]]);
        if ($method !== 'card') {
            $out[] = $this->answer(['data' => ['id' => 'pm_' . $intent]]);
            $out[] = $this->answer(['data' => ['id' => $intent, 'attributes' => ['status' => 'awaiting_next_action', 'next_action' => ['type' => 'redirect', 'redirect' => ['url' => 'https://pay.example.com/approve/' . $intent]]]]]);
        }
        return $out;
    }

    private function subState(string $sub, string $status, ?string $invoice, bool $paid, int $amount = 35000): GuzzleResponse
    {
        return $this->answer(['data' => ['id' => $sub, 'attributes' => ['status' => $status, 'latest_invoice' => ['id' => $invoice, 'status' => $paid ? 'paid' : 'open', 'amount' => $amount, 'payment_intent' => ['id' => 'pi_x', 'status' => 'succeeded']]]]]);
    }

    private function buy(string $plan = 'sweetheart', string $period = 'month'): Order
    {
        $response = $this->submit('POST', '/dashboard/pages/' . $this->page->slug . '/orders', ['plan' => $plan, 'period' => $period]);
        $order = Order::query()->orderBy('id', 'desc')->first();
        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame('/dashboard/orders/' . $order->reference, $response->getHeaderLine('Location'));
        return $order;
    }

    private function pay(Order $order, array $data): ResponseInterface
    {
        return $this->submit('POST', '/dashboard/orders/' . $order->reference . '/pay', $data);
    }

    private function card(Order $order): ResponseInterface
    {
        return $this->ajax('POST', '/dashboard/orders/' . $order->reference . '/card');
    }

    private function fresh(Order $order): Order
    {
        return Order::query()->withoutCache()->find($order->id);
    }

    private function freshPage(): Page
    {
        return Page::query()->withoutCache()->find($this->page->id);
    }

    private function paidWebhook(string $intent, int $amount, string $currency = 'PHP'): ResponseInterface
    {
        $payload = ['data' => ['id' => 'evt_1', 'type' => 'event', 'attributes' => [
            'type' => 'payment.paid', 'livemode' => false,
            'data' => ['id' => 'pay_1', 'type' => 'payment', 'attributes' => ['payment_intent_id' => $intent, 'amount' => $amount, 'currency' => $currency, 'status' => 'paid']],
        ]]];
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', '1700000000.' . $body, self::WEBHOOK_SECRET);
        $request = (new ServerRequest('POST', '/api/webhooks/paymongo', ['Paymongo-Signature' => "t=1700000000,te={$signature},li=", 'Content-Type' => 'application/json']))
            ->withBody((new Psr17Factory())->createStream($body));
        return $this->send($request);
    }

    /** @return list<array{string,string}> method and path of the calls made to PayMongo so far */
    private function calls(): array
    {
        return array_map(static fn (array $h): array => [$h['request']->getMethod(), $h['request']->getUri()->getPath()], $this->history);
    }

    // ---- the page ----

    public function testPayMongoIsTheDefaultOnceItsKeyIsSet(): void
    {
        $this->app->make(Repository::class)->set('saas.payments.driver', 'auto');
        $this->app->instance(PayMongoGateway::class, new PayMongoGateway('', '', [], 'https://love.test'));
        $this->assertSame('manual', $this->app->make(PaymentGateway::class)->name());
        $this->app->instance(PayMongoGateway::class, new PayMongoGateway('sk_test_x', '', [], 'https://love.test'));
        $this->assertSame('paymongo', $this->app->make(PaymentGateway::class)->name());
    }

    public function testTheOrderPageListsTheFourWaysToPayEachWithItsFormBeneathIt(): void
    {
        $this->usePayMongo();
        $order = $this->buy();
        $html = (string) $this->get('/dashboard/orders/' . $order->reference)->getBody();

        foreach (['GCash', 'PayMaya', 'Credit card', 'Mastercard'] as $label) {
            $this->assertStringContainsString('<b>' . $label . '</b>', $html);
        }
        $this->assertSame(4, substr_count($html, 'class="pay-radio"'));
        $this->assertSame(4, substr_count($html, 'class="pay-panel"'));
        // e-wallets ask for the mobile number and offer subscribe (selected) or one-time
        $this->assertSame(2, substr_count($html, 'name="phone"'));
        $this->assertSame(2, substr_count($html, 'name="billing" value="subscription" checked'));
        $this->assertSame(2, substr_count($html, 'name="billing" value="one_time"'));
        // cards: the form says it subscribes, and its fields have no name: the number is never posted to this server
        $this->assertSame(2, substr_count($html, 'data-card-form'));
        $this->assertStringContainsString('goes straight to the payment provider', $html);
        $this->assertStringNotContainsString('name="card', $html);
        $this->assertStringNotContainsString('name="cc', $html);
        $this->assertStringNotContainsString('name="number', $html);
        $this->assertSame([], $this->history, 'nothing is asked of PayMongo before the customer chooses');
    }

    public function testTheWordingSaysWhetherARenewalIsAutomaticOrApproved(): void
    {
        $this->usePayMongo([], false);
        $html = (string) $this->get('/dashboard/orders/' . $this->buy()->reference)->getBody();
        $this->assertStringContainsString('you will get a reminder and approve each payment with GCash', $html);
        $this->assertStringContainsString('approve each payment with your card', $html);
        $this->assertStringNotContainsString('Renews automatically', $html);

        $this->usePayMongo([], true);
        $html = (string) $this->get('/dashboard/orders/' . $this->buy()->reference)->getBody();
        $this->assertStringContainsString('approve each payment with GCash', $html, 'GCash cannot be charged by itself, whatever is enabled');
        $this->assertSame(3, substr_count($html, 'Renews automatically every month. Cancel any time in your account.'), 'PayMaya and the two card forms');
    }

    public function testAPlanBoughtOnceIsOneTimeEverywhere(): void
    {
        $this->usePayMongo();
        $html = (string) $this->get('/dashboard/orders/' . $this->buy('forever', 'once')->reference)->getBody();
        $this->assertStringNotContainsString('name="billing"', $html);
        $this->assertStringContainsString('this plan never expires', $html);
    }

    // ---- GCash and PayMaya ----

    public function testAGCashPaymentIsStartedWithTheNumberAndTheCustomerSentOnToTheWallet(): void
    {
        $this->usePayMongo($this->walletAnswers());
        $order = $this->buy();
        $response = $this->pay($order, ['method' => 'gcash', 'phone' => '0917 123 4567', 'billing' => 'one_time']);

        $this->assertSame('/dashboard/orders/' . $order->reference . '?go=1', $response->getHeaderLine('Location'));
        $this->assertSame([['POST', '/v1/payment_intents'], ['POST', '/v1/payment_methods'], ['POST', '/v1/payment_intents/pi_1/attach']], $this->calls());
        [$intent, $method, $attach] = array_map(static fn (array $h) => $h['request'], $this->history);
        $this->assertSame('Basic ' . base64_encode('sk_test_sec:'), $intent->getHeaderLine('Authorization'));
        $attributes = json_decode((string) $intent->getBody(), true)['data']['attributes'];
        $this->assertSame([35000, 'PHP', ['gcash']], [$attributes['amount'], $attributes['currency'], $attributes['payment_method_allowed']]);
        $this->assertArrayNotHasKey('payment_method_options', $attributes);
        $this->assertSame('Basic ' . base64_encode('pk_test_pub:'), $method->getHeaderLine('Authorization'), 'the method and the attach use the public key');
        $sent = json_decode((string) $method->getBody(), true)['data']['attributes'];
        $this->assertSame('gcash', $sent['type']);
        $this->assertSame(['name' => 'Owner', 'email' => 'owner@example.com', 'phone' => '+639171234567'], $sent['billing']);
        $attach = json_decode((string) $attach->getBody(), true)['data']['attributes'];
        $this->assertSame(['pm_pi_1', 'pi_1_ck', 'https://love.test/dashboard/orders/' . $order->reference], [$attach['payment_method'], $attach['client_key'], $attach['return_url']]);

        $fresh = $this->fresh($order);
        $this->assertSame(['gcash', 'one_time', 'pi_1'], [$fresh->method, $fresh->billing, $fresh->provider_ref]);
        $attempt = PaymentAttempt::query()->where('order_id', $order->id)->first();
        $this->assertSame(['pi_1', 'https://pay.example.com/approve/pi_1', null], [$attempt->provider_ref, $attempt->redirect_url, $attempt->subscription_ref]);

        $html = (string) $this->get('/dashboard/orders/' . $order->reference . '?go=1')->getBody();
        $this->assertStringContainsString('href="https://pay.example.com/approve/pi_1"', $html);
        $this->assertStringContainsString('Continue to GCash', $html);
        $this->assertStringContainsString('data-go="1"', $html);
        $this->assertStringContainsString('data-go="0"', (string) $this->get('/dashboard/orders/' . $order->reference)->getBody(), 'coming back from the wallet does not send them again');
    }

    public function testNothingThatIdentifiesAPaymentIsPlainInTheDatabase(): void
    {
        $this->usePayMongo([...$this->subscriptionAnswers('paymaya'), $this->subState('sub_1', 'active', 'inv_1', true)], true);
        $order = $this->buy();
        $this->pay($order, ['method' => 'paymaya', 'phone' => '09171234567', 'billing' => 'subscription']);
        $this->paidWebhook('pi_s1', 35000);
        $this->assertSame(Order::PAID, $this->fresh($order)->status);

        $plain = ['pi_s1', 'sub_1', 'inv_1', 'cus_1', 'plan_1', 'pay.example.com', 'approve'];
        foreach (\App\Services\PaymentVault::SEALED as $table => $columns) {
            foreach ($this->db()->table($table)->get() as $row) {
                foreach ($columns as $column) {
                    foreach ($plain as $needle) {
                        $this->assertStringNotContainsString($needle, (string) ($row[$column] ?? ''), "$table.$column holds $needle in the clear");
                    }
                }
            }
        }
        // ... and the app still works with them: the subscription was started from the sealed ids
        $this->assertSame('sub_1', Subscription::query()->withoutCache()->first()->provider_ref);
        $this->assertSame('pi_s1', $this->fresh($order)->provider_ref);
    }

    public function testWalletBillingDefaultsToSubscribeAndIsOneTimeWhenAsked(): void
    {
        $this->usePayMongo([...$this->walletAnswers('pi_a'), ...$this->walletAnswers('pi_b'), ...$this->walletAnswers('pi_c')]);
        $order = $this->buy();

        $this->pay($order, ['method' => 'paymaya', 'phone' => '09171234567']);
        $this->assertSame('subscription', $this->fresh($order)->billing, 'subscribe is the default');
        $this->pay($order, ['method' => 'paymaya', 'phone' => '09171234567', 'billing' => 'one_time']);
        $this->assertSame('one_time', $this->fresh($order)->billing);
        $this->pay($order, ['method' => 'gcash', 'phone' => '+63 917 123 4567', 'billing' => 'nonsense']);
        $this->assertSame('subscription', $this->fresh($order)->billing);
        $this->assertSame(3, PaymentAttempt::query()->count());
    }

    public function testTheMobileNumberIsChecked(): void
    {
        $this->usePayMongo($this->walletAnswers());
        $order = $this->buy();
        foreach (['', '12345', '0817 123 4567', '09171234', 'abc'] as $phone) {
            $this->pay($order, ['method' => 'gcash', 'phone' => $phone]);
        }
        $this->assertSame([], $this->history, 'nothing is asked of PayMongo');
        $html = (string) $this->get('/dashboard/orders/' . $order->reference)->getBody();
        $this->assertStringNotContainsString('Waiting for your payment', $html);
        $this->assertSame('+639171234567', \App\Http\Controllers\Dashboard\OrdersController::mobile('0917-123-4567'));
        $this->assertSame('+639171234567', \App\Http\Controllers\Dashboard\OrdersController::mobile('9171234567'));
        $this->assertNull(\App\Http\Controllers\Dashboard\OrdersController::mobile('+1 202 555 0100'));
    }

    public function testTheMethodMustBeOneOfThem(): void
    {
        $this->usePayMongo($this->walletAnswers());
        $order = $this->buy();
        foreach ([[], ['method' => 'cash'], ['method' => 'bank'], ['method' => 'card', 'phone' => '09171234567']] as $data) {
            $this->pay($order, $data);
        }
        $this->assertSame([], $this->history);
        $this->assertNull($this->fresh($order)->method);
    }

    public function testWhenPayMongoFailsTheCustomerHearsAndNothingIsKept(): void
    {
        $this->usePayMongo([$this->answer(['errors' => []], 500)]);
        $order = $this->buy();
        $response = $this->pay($order, ['method' => 'gcash', 'phone' => '09171234567']);

        $this->assertSame('/dashboard/orders/' . $order->reference, $response->getHeaderLine('Location'));
        $this->assertSame(0, PaymentAttempt::query()->count());
        $this->assertStringContainsString('could not start the checkout', (string) $this->get('/dashboard/orders/' . $order->reference)->getBody());
    }

    // ---- cards ----

    public function testACardPaymentIsStartedWithoutTheCardAndTheBrowserGetsWhatItNeeds(): void
    {
        $this->usePayMongo($this->cardAnswers());
        $order = $this->buy();
        $response = $this->card($order);

        $this->assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);
        $this->assertSame(['pi_c', 'pi_c_ck', 'pk_test_pub', 'https://love.test/dashboard/orders/' . $order->reference, 'Owner', 'owner@example.com'], [$body['intent'], $body['client_key'], $body['public_key'], $body['return_url'], $body['name'], $body['email']]);
        $this->assertSame([['POST', '/v1/payment_intents']], $this->calls(), 'the server only makes the intent; the browser sends the card');
        $attributes = json_decode((string) $this->history[0]['request']->getBody(), true)['data']['attributes'];
        $this->assertSame(['card'], $attributes['payment_method_allowed']);
        $this->assertSame('any', $attributes['payment_method_options']['card']['request_three_d_secure']);

        $fresh = $this->fresh($order);
        $this->assertSame(['card', 'subscription', 'pi_c'], [$fresh->method, $fresh->billing, $fresh->provider_ref], 'a card always subscribes');
        $this->assertNull(PaymentAttempt::query()->first()->redirect_url);
        $this->assertStringContainsString('Confirming your card payment', (string) $this->get('/dashboard/orders/' . $order->reference)->getBody());
    }

    public function testACardOnAPlanBoughtOnceIsOneTime(): void
    {
        $this->usePayMongo($this->cardAnswers());
        $order = $this->buy('forever', 'once');
        $this->card($order);
        $this->assertSame('one_time', $this->fresh($order)->billing);
    }

    public function testACardFailureIsAnswered502WithAMessage(): void
    {
        $this->usePayMongo([$this->answer(['errors' => []], 500)]);
        $order = $this->buy();
        $response = $this->card($order);
        $this->assertSame(502, $response->getStatusCode());
        $this->assertStringContainsString('could not start', $this->decode($response)['message']);
        $this->assertSame(0, PaymentAttempt::query()->count());
    }

    public function testOnlyTheOwnerOfAPendingOrderCanStartOrWatchAPayment(): void
    {
        $this->usePayMongo($this->walletAnswers());
        $order = $this->buy();
        $this->submit('POST', '/logout');
        $this->signIn($this->makeUser('other@example.com'));
        $this->assertSame(404, $this->pay($order, ['method' => 'gcash', 'phone' => '09171234567'])->getStatusCode());
        $this->assertSame(404, $this->card($order)->getStatusCode());
        $this->assertSame(404, $this->get('/dashboard/orders/' . $order->reference . '/status')->getStatusCode());
        $this->assertSame([], $this->history);
    }

    // ---- photos are bought the same way ----

    public function testBuyingPhotosGoesToTheSamePaymentPageAndAddsThemWhenPaid(): void
    {
        $this->usePayMongo($this->walletAnswers('pi_ph'));
        $response = $this->submit('POST', '/dashboard/photo-packs', ['packs' => '2', 'page' => $this->page->slug]);
        $order = Order::query()->orderBy('id', 'desc')->first();
        $this->assertSame('/dashboard/orders/' . $order->reference, $response->getHeaderLine('Location'), 'the page where a plan is paid');
        $this->assertSame([Order::PHOTO_PACK, 24, 20000, 'paymongo'], [$order->plan, $order->photos, $order->amount, $order->provider]);

        $html = (string) $this->get('/dashboard/orders/' . $order->reference)->getBody();
        $this->assertStringContainsString('24 more photos for your account', $html);
        $this->assertSame(4, substr_count($html, 'class="pay-radio"'), 'GCash, PayMaya and the cards');
        $this->assertStringContainsString('the photos are paid once and are yours for good', $html);
        $this->assertStringNotContainsString('name="billing"', $html, 'nothing to subscribe to');

        $this->pay($order, ['method' => 'gcash', 'phone' => '09171234567', 'billing' => 'subscription']);
        $this->assertSame('one_time', $this->fresh($order)->billing, 'photos are never a subscription');
        $this->paidWebhook('pi_ph', 20000);

        $this->assertSame(Order::PAID, $this->fresh($order)->status);
        $this->assertSame(0, Subscription::query()->count());
        $this->assertSame('free', $this->freshPage()->plan, 'a pack never changes a plan');
        $usage = $this->app->make(\App\Services\PhotoAllowance::class)->of($this->owner->id);
        $this->assertSame([6, 24, 30], [$usage['included'], $usage['bought'], $usage['total']]);
    }

    public function testThePhotosBoxOffersToBuyPhotosAndToUpgrade(): void
    {
        $this->usePayMongo();
        $html = (string) $this->get('/dashboard/pages/' . $this->page->slug)->getBody();
        $this->assertStringContainsString('Subscribe to a plan to add photos.', $html, 'photos come after a plan');
        $this->assertStringNotContainsString('action="/dashboard/photo-packs"', $html);

        $this->freshPage()->forceFill(['plan' => 'sweet', 'expires_at' => '2099-01-01 00:00:00', 'published_at' => '2026-01-01 00:00:00'])->save();
        $html = (string) $this->get('/dashboard/pages/' . $this->page->slug)->getBody();
        $this->assertStringContainsString('Need more photos? 12 for ₱100', $html);
        $this->assertStringContainsString('action="/dashboard/photo-packs"', $html);
        $this->assertStringContainsString('<a class="btn btn-secondary btn-sm" href="#plan">Upgrade plan</a>', $html);

        $this->freshPage()->forceFill(['plan' => 'forever', 'expires_at' => null, 'published_at' => '2026-01-01 00:00:00'])->save();
        $html = (string) $this->get('/dashboard/pages/' . $this->page->slug)->getBody();
        $this->assertStringContainsString('Need more photos?', $html);
        $this->assertStringNotContainsString('Upgrade plan', $html, 'Forever has nothing above it');

        $dashboard = (string) $this->get('/dashboard')->getBody();
        $this->assertStringContainsString('Need more photos?', $dashboard);
        $this->assertStringNotContainsString('Upgrade plan', $dashboard);
    }

    // ---- being paid ----

    public function testAnyPaymentOfTheOrderIsCreditedByTheWebhook(): void
    {
        $this->usePayMongo([...$this->walletAnswers('pi_old'), ...$this->walletAnswers('pi_new')]);
        $order = $this->buy();
        $this->pay($order, ['method' => 'gcash', 'phone' => '09171234567', 'billing' => 'one_time']);
        $this->pay($order, ['method' => 'paymaya', 'phone' => '09171234567', 'billing' => 'one_time']); // changed their mind

        $this->assertSame(200, $this->paidWebhook('pi_old', 35000)->getStatusCode(), 'the first wallet was approved after all');
        $paid = $this->fresh($order);
        $this->assertSame([Order::PAID, 'pi_old'], [$paid->status, $paid->provider_ref]);
        $this->assertSame('sweetheart', $this->freshPage()->plan);
        $expires = $this->freshPage()->expires_at;
        $this->assertSame(200, $this->paidWebhook('pi_new', 35000)->getStatusCode(), 'a second delivery changes nothing');
        $this->assertEquals($expires, $this->freshPage()->expires_at);
        $this->assertStringContainsString('Paid. Thank you!', (string) $this->get('/dashboard/orders/' . $order->reference)->getBody());
        $this->assertMatchesRegularExpression('#tag-current">Current</span>.*?Renew Sweetheart#s', (string) $this->get('/dashboard/pages/' . $this->page->slug)->getBody(), 'once paid, the plan shows as Current');
        $this->assertSame(0, Subscription::query()->count(), 'a one-time payment makes no subscription');
    }

    public function testAWrongAmountOrAnUnknownPaymentIsNotCredited(): void
    {
        $this->usePayMongo($this->walletAnswers());
        $order = $this->buy();
        $this->pay($order, ['method' => 'gcash', 'phone' => '09171234567']);
        $this->assertSame(200, $this->paidWebhook('pi_1', 100)->getStatusCode());
        $this->assertSame(200, $this->paidWebhook('pi_1', 35000, 'USD')->getStatusCode());
        $this->assertSame(200, $this->paidWebhook('pi_unknown', 35000)->getStatusCode());
        $this->assertSame(Order::PENDING, $this->fresh($order)->status);
    }

    public function testThePageAsksPayMongoWhenTheWebhookIsLate(): void
    {
        $this->usePayMongo([...$this->cardAnswers('pi_c'),
            $this->answer(['data' => ['id' => 'pi_c', 'attributes' => ['status' => 'processing', 'amount' => 35000, 'currency' => 'PHP']]]),
            $this->answer(['data' => ['id' => 'pi_c', 'attributes' => ['status' => 'succeeded', 'amount' => 35000, 'currency' => 'PHP']]])]);
        $order = $this->buy();
        $this->card($order);

        $waiting = $this->decode($this->get('/dashboard/orders/' . $order->reference . '/status'));
        $this->assertSame([false, 'pending'], [$waiting['paid'], $waiting['status']]);
        $done = $this->decode($this->get('/dashboard/orders/' . $order->reference . '/status'));
        $this->assertSame([true, 'paid'], [$done['paid'], $done['status']]);
        $this->assertSame('sweetheart', $this->freshPage()->plan);
    }

    public function testAnExpiredPaymentGivesWayToTheChoicesAgain(): void
    {
        $this->usePayMongo($this->walletAnswers());
        $order = $this->buy();
        $this->pay($order, ['method' => 'gcash', 'phone' => '09171234567']);
        PaymentAttempt::query()->where('order_id', $order->id)->first()->forceFill(['expires_at' => '2020-01-01 00:00:00'])->save();

        $html = (string) $this->get('/dashboard/orders/' . $order->reference)->getBody();
        $this->assertStringContainsString('was not completed', $html);
        $this->assertSame(4, substr_count($html, 'class="pay-radio"'));
        $this->assertStringNotContainsString('id="order-wait"', $html);
    }

    // ---- subscriptions the customer approves each time (GCash, or when PayMongo cannot charge by itself) ----

    private function payManualSubscription(string $intent = 'pi_g1'): Order
    {
        $order = $this->buy();
        $this->pay($order, ['method' => 'gcash', 'phone' => '09171234567', 'billing' => 'subscription']);
        $this->paidWebhook($intent, $order->amount);
        $this->assertSame(Order::PAID, $this->fresh($order)->status);
        return $this->fresh($order);
    }

    public function testAGCashSubscriptionIsManualEvenWhenPayMongoCanChargeAutomatically(): void
    {
        $this->usePayMongo($this->walletAnswers('pi_g1'), true);
        $order = $this->payManualSubscription();

        $this->assertSame(['POST /v1/payment_intents', 'POST /v1/payment_methods', 'POST /v1/payment_intents/pi_g1/attach'], array_map(fn ($c) => implode(' ', $c), $this->calls()), 'no PayMongo subscription for GCash');
        $sub = Subscription::query()->first();
        $this->assertSame(['gcash', 'manual', null, 'active'], [$sub->method, $sub->mode, $sub->provider_ref, $sub->status]);
        $this->assertSame($sub->id, $order->subscription_id);
        $html = (string) $this->get('/dashboard/pages/' . $this->page->slug)->getBody();
        $this->assertStringContainsString('Subscribed: Sweetheart, renews every month', $html);
        $this->assertStringContainsString('you approve each payment', (string) $this->get('/dashboard')->getBody());
    }

    public function testAManualSubscriptionRenewsByAnOrderThatIsPaidAgain(): void
    {
        $this->usePayMongo([...$this->walletAnswers('pi_g1'), ...$this->walletAnswers('pi_g2')]);
        $first = $this->payManualSubscription();
        $subscriptions = $this->app->make(Subscriptions::class);

        $this->assertSame(0, $subscriptions->createDueRenewals(), 'a month away: not due');
        $this->freshPage()->forceFill(['expires_at' => gmdate('Y-m-d H:i:s', time() + 2 * 86400)])->save();
        $this->assertSame(1, $subscriptions->createDueRenewals());
        $this->assertSame(0, $subscriptions->createDueRenewals(), 'only once');
        $renewal = Order::query()->where('subscription_id', $first->subscription_id)->where('status', 'pending')->first();
        $this->assertInstanceOf(Order::class, $renewal);
        $this->assertSame(['sweetheart', 'month', 35000, 'subscription', 'gcash'], [$renewal->plan, $renewal->period, $renewal->amount, $renewal->billing, $renewal->method]);
        $this->assertStringContainsString('A subscription renewal is due', (string) $this->get('/dashboard')->getBody());
        $this->assertStringContainsString('Renewal due: pay ₱350', (string) $this->get('/dashboard/pages/' . $this->page->slug)->getBody());

        $this->pay($renewal, ['method' => 'gcash', 'phone' => '09171234567', 'billing' => 'one_time']);
        $this->assertSame('subscription', $this->fresh($renewal)->billing, 'a renewal stays a subscription');
        $this->paidWebhook('pi_g2', 35000);
        $this->assertSame(Order::PAID, $this->fresh($renewal)->status);
        $this->assertSame(1, Subscription::query()->count());
        $this->assertGreaterThan(new \DateTimeImmutable('+30 days'), $this->freshPage()->expires_at);
    }

    // ---- subscriptions PayMongo charges by itself (cards and Maya) ----

    public function testACardSubscriptionStartsAPayMongoSubscriptionAndKeepsItsIds(): void
    {
        $this->usePayMongo($this->subscriptionAnswers('card'), true);
        $order = $this->buy();
        $response = $this->card($order);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('pi_s1_ck', $this->decode($response)['client_key']);
        $this->assertSame(['POST /v1/customers', 'POST /v1/subscriptions/plans', 'POST /v1/subscriptions', 'GET /v1/payment_intents/pi_s1'], array_map(fn ($c) => implode(' ', $c), $this->calls()));
        $customer = json_decode((string) $this->history[0]['request']->getBody(), true)['data']['attributes'];
        $this->assertSame(['Owner', 'owner@example.com'], [$customer['first_name'], $customer['email']]);
        $plan = json_decode((string) $this->history[1]['request']->getBody(), true)['data']['attributes'];
        $this->assertSame([35000, 'PHP', 'monthly', 1], [$plan['amount'], $plan['currency'], $plan['interval'], $plan['interval_count']]);
        $this->assertSame(['cus_1', 'plan_1'], [ProviderRef::lookup('paymongo', 'customer', 'user:' . $this->owner->id), ProviderRef::lookup('paymongo', 'plan', 'sweetheart:month:35000')]);
        $subscription = json_decode((string) $this->history[2]['request']->getBody(), true)['data']['attributes'];
        $this->assertSame(['cus_1', 'plan_1'], [$subscription['customer_id'], $subscription['plan_id']]);

        $attempt = PaymentAttempt::query()->first();
        $this->assertSame(['pi_s1', 'sub_1'], [$attempt->provider_ref, $attempt->subscription_ref]);
    }

    public function testPayMayaSubscribesAutomaticallyAndTheCustomerApprovesTheFirstPayment(): void
    {
        $this->usePayMongo($this->subscriptionAnswers('paymaya'), true);
        $order = $this->buy();
        $response = $this->pay($order, ['method' => 'paymaya', 'phone' => '09171234567', 'billing' => 'subscription']);

        $this->assertSame('/dashboard/orders/' . $order->reference . '?go=1', $response->getHeaderLine('Location'));
        $this->assertSame(['POST /v1/customers', 'POST /v1/subscriptions/plans', 'POST /v1/subscriptions', 'GET /v1/payment_intents/pi_s1', 'POST /v1/payment_methods', 'POST /v1/payment_intents/pi_s1/attach'], array_map(fn ($c) => implode(' ', $c), $this->calls()));
        $this->assertSame('paymaya', json_decode((string) $this->history[4]['request']->getBody(), true)['data']['attributes']['type']);
        $attempt = PaymentAttempt::query()->first();
        $this->assertSame(['sub_1', 'https://pay.example.com/approve/pi_s1'], [$attempt->subscription_ref, $attempt->redirect_url]);
    }

    public function testThePlanAndCustomerAreMadeOnceAndReused(): void
    {
        $this->usePayMongo([...$this->subscriptionAnswers('card'), ...$this->subscriptionAnswers('card', 'sub_2', 'pi_s2', false)], true);
        $this->card($this->buy());
        $this->card($this->buy('sweetheart', 'month')); // a second order for the same price
        $this->assertSame(1, count(array_filter($this->calls(), fn ($c) => $c === ['POST', '/v1/customers'])));
        $this->assertSame(1, count(array_filter($this->calls(), fn ($c) => $c === ['POST', '/v1/subscriptions/plans'])));
    }

    public function testPayingTheFirstInvoiceStartsAnAutomaticSubscription(): void
    {
        $this->usePayMongo([...$this->subscriptionAnswers('card'), $this->subState('sub_1', 'active', 'inv_1', true)], true);
        $order = $this->buy();
        $this->card($order);
        $this->paidWebhook('pi_s1', 35000);

        $this->assertSame(Order::PAID, $this->fresh($order)->status);
        $sub = Subscription::query()->first();
        $this->assertSame(['card', 'auto', 'sub_1', 'inv_1', 'active'], [$sub->method, $sub->mode, $sub->provider_ref, $sub->last_invoice_ref, $sub->status]);
        $html = (string) $this->get('/dashboard/pages/' . $this->page->slug)->getBody();
        $this->assertStringContainsString('It renews automatically', $html);
        $this->assertStringNotContainsString('Renewal due', $html);
    }

    /** An automatic subscription whose first invoice is already counted. */
    private function autoSubscription(): Subscription
    {
        $this->usePayMongo([...$this->subscriptionAnswers('card'), $this->subState('sub_1', 'active', 'inv_1', true)], true);
        $order = $this->buy();
        $this->card($order);
        $this->paidWebhook('pi_s1', 35000);
        return Subscription::query()->first();
    }

    public function testAPeriodPayMongoChargedByItselfExtendsThePageOnce(): void
    {
        $sub = $this->autoSubscription();
        $subscriptions = $this->app->make(Subscriptions::class);
        $before = $this->freshPage()->expires_at;
        $this->freshPage()->forceFill(['expires_at' => gmdate('Y-m-d H:i:s', time() + 86400)])->save();

        $this->history = [];
        $this->usePayMongo([$this->subState('sub_1', 'active', 'inv_1', true), $this->subState('sub_1', 'active', 'inv_2', true), $this->subState('sub_1', 'active', 'inv_2', true)], true);
        $this->assertSame(0, $subscriptions->syncAuto(), 'inv_1 is already counted');
        $this->assertSame(1, $subscriptions->syncAuto(), 'inv_2 is a new period');
        $this->assertSame(0, $subscriptions->syncAuto(), 'and counted once');

        $this->assertGreaterThan(new \DateTimeImmutable('+25 days'), $this->freshPage()->expires_at);
        $renewal = Order::query()->where('subscription_id', $sub->id)->where('status', 'paid')->orderBy('id', 'desc')->first();
        $this->assertSame(['inv_2', 'subscription', 'card', 35000], [$renewal->provider_ref, $renewal->billing, $renewal->method, $renewal->amount]);
        $fresh = Subscription::query()->withoutCache()->find($sub->id);
        $this->assertSame(['inv_2', 'auto', 'sub_1', 'active'], [$fresh->last_invoice_ref, $fresh->mode, $fresh->provider_ref, $fresh->status], 'crediting a period keeps it automatic');
        $this->assertNotNull($before);
    }

    public function testASubscriptionPayMongoCancelledOrGaveUpOnEndsHere(): void
    {
        $sub = $this->autoSubscription();
        $subscriptions = $this->app->make(Subscriptions::class);
        $this->usePayMongo([$this->subState('sub_1', 'unpaid', 'inv_1', false)], true);
        $subscriptions->syncAuto();
        $this->assertSame('ended', Subscription::query()->withoutCache()->find($sub->id)->status);
    }

    public function testAnOutageDoesNotChangeASubscription(): void
    {
        $sub = $this->autoSubscription();
        $this->usePayMongo([$this->answer(['errors' => []], 500)], true);
        $this->assertSame(0, $this->app->make(Subscriptions::class)->syncAuto());
        $this->assertSame('active', Subscription::query()->withoutCache()->find($sub->id)->status);
    }

    // ---- unsubscribing ----

    public function testUnsubscribingAnAutomaticSubscriptionStopsPayMongoFirst(): void
    {
        $sub = $this->autoSubscription();
        $this->usePayMongo([$this->answer(['data' => ['id' => 'sub_1', 'attributes' => ['status' => 'cancelled']]])], true);
        $response = $this->submit('POST', '/dashboard/subscriptions/' . $sub->id . '/cancel');

        $this->assertSame('/dashboard', $response->getHeaderLine('Location'));
        $this->assertSame([['DELETE', '/v1/subscriptions/sub_1']], $this->calls());
        $this->assertSame('cancelled', Subscription::query()->withoutCache()->find($sub->id)->status);
        $this->assertSame('sweetheart', $this->freshPage()->plan, 'the time already paid stays');
    }

    public function testIfPayMongoCannotBeToldToStopTheSubscriptionStaysAndTheCustomerHears(): void
    {
        $sub = $this->autoSubscription();
        $this->usePayMongo([$this->answer(['errors' => []], 500)], true);
        $this->submit('POST', '/dashboard/subscriptions/' . $sub->id . '/cancel');
        $this->assertSame('active', Subscription::query()->withoutCache()->find($sub->id)->status, 'never say cancelled while it can still be charged');
        $this->assertStringContainsString('could not start', (string) $this->get('/dashboard')->getBody());
    }

    public function testTheDashboardListsTheSubscriptionsWithAnUnsubscribeButton(): void
    {
        $this->usePayMongo($this->walletAnswers('pi_g1'));
        $this->payManualSubscription();
        $sub = Subscription::query()->first();
        $html = (string) $this->get('/dashboard')->getBody();
        $this->assertStringContainsString('Your subscriptions', $html);
        $this->assertStringContainsString('action="/dashboard/subscriptions/' . $sub->id . '/cancel"', $html);
        $this->assertStringContainsString('Unsubscribe', $html);

        $this->submit('POST', '/dashboard/subscriptions/' . $sub->id . '/cancel');
        $this->assertSame('cancelled', Subscription::query()->withoutCache()->find($sub->id)->status);
        $this->assertStringNotContainsString('Your subscriptions', (string) $this->get('/dashboard')->getBody());
    }

    public function testASubscriptionCanAlsoBeStoppedFromItsPage(): void
    {
        $this->usePayMongo($this->walletAnswers('pi_g1'));
        $this->payManualSubscription();
        $this->freshPage()->forceFill(['expires_at' => gmdate('Y-m-d H:i:s', time() + 86400)])->save();
        $this->app->make(Subscriptions::class)->createDueRenewals();
        $renewal = Order::query()->where('status', 'pending')->first();
        $this->assertInstanceOf(Order::class, $renewal);

        $response = $this->submit('POST', '/dashboard/pages/' . $this->page->slug . '/subscription/cancel');
        $this->assertSame('/dashboard/pages/' . $this->page->slug . '#plan', $response->getHeaderLine('Location'));
        $this->assertSame('cancelled', Subscription::query()->first()->status);
        $this->assertSame(Order::CANCELLED, $this->fresh($renewal)->status, 'the unpaid renewal is dropped');
        $this->assertSame(0, $this->app->make(Subscriptions::class)->createDueRenewals());
        $this->assertSame(404, $this->submit('POST', '/dashboard/pages/' . $this->page->slug . '/subscription/cancel')->getStatusCode());
    }

    public function testNobodyCanUnsubscribeSomeoneElse(): void
    {
        $this->usePayMongo($this->walletAnswers('pi_g1'));
        $this->payManualSubscription();
        $sub = Subscription::query()->first();
        $this->submit('POST', '/logout');
        $this->signIn($this->makeUser('other@example.com'));
        $this->assertSame(404, $this->submit('POST', '/dashboard/subscriptions/' . $sub->id . '/cancel')->getStatusCode());
        $this->assertSame(404, $this->submit('POST', '/dashboard/pages/' . $this->page->slug . '/subscription/cancel')->getStatusCode());
        $this->assertSame('active', Subscription::query()->withoutCache()->find($sub->id)->status);
    }

    public function testAHigherPlanEndsTheSubscriptionAndStopsPayMongoCharging(): void
    {
        $sub = $this->autoSubscription();
        $this->freshPage()->forceFill(['plan' => 'forever', 'expires_at' => null])->save();
        $this->usePayMongo([$this->answer(['data' => ['id' => 'sub_1', 'attributes' => ['status' => 'cancelled']]])], true);
        $this->app->make(Subscriptions::class)->syncAuto();

        $this->assertSame([['DELETE', '/v1/subscriptions/sub_1']], $this->calls());
        $this->assertSame('ended', Subscription::query()->withoutCache()->find($sub->id)->status);
    }

    public function testDeletingThePageEndsItsSubscription(): void
    {
        $this->usePayMongo($this->walletAnswers('pi_g1'));
        $this->payManualSubscription();
        $this->submit('DELETE', '/dashboard/pages/' . $this->page->slug, ['confirm' => 'delete']);
        $this->assertSame('ended', Subscription::query()->first()->status);
    }
}

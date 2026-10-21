<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Screen;
use App\Models\Order;
use App\Models\Page;
use App\Services\Billing;
use App\Services\Designs;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\HostedGateway;
use App\Services\Payments\PaymentException;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\OnlineGateway;
use App\Services\Subscriptions;
use App\Models\PaymentAttempt;
use App\Models\Subscription;
use App\Services\PhotoAllowance;
use App\Services\Plans;
use Naluz\Auth\Auth;
use Naluz\Http\HttpException;
use Naluz\Http\Request;
use Naluz\Http\Response;
use Naluz\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Buying a plan for a page, or photos for the account: create (or reuse) the order, then hand over to the payment
 * gateway.
 */
final class OrdersController
{
    use OwnsPages;

    public function store(string $slug, ServerRequestInterface $request, Auth $auth, Plans $plans, Billing $billing, PaymentGateway $gateway): ResponseInterface
    {
        $page = $this->ownedPage($auth, $slug);
        $body = Request::body($request);
        $plan = (string) ($body['plan'] ?? '');
        $period = (string) ($body['period'] ?? '');
        if (!$plans->canBuy($page, $plan, $period)) {
            throw new ValidationException(['plan' => ['That plan cannot be bought for this page.']], ['plan' => $plan]);
        }
        // the page's template has to come with the plan, or the page would go live in another one
        $template = Designs::layout($page->layout);
        if (!$plans->allowsTemplate($plan, $template)) {
            throw new ValidationException(['plan' => [sprintf(
                'Your page uses the %s template, which comes with %s. Choose one of those plans, or pick another template first.',
                Designs::LAYOUTS[$template]['name'],
                $plans->names($plans->plansWithTemplate($template)),
            )]], ['plan' => $plan]);
        }

        // Clicking "buy" twice must not create two orders to pay.
        $order = Order::query()->where('user_id', (int) $auth->id())->where('page_id', $page->id)->where('plan', $plan)
            ->where('period', $period)->where('status', Order::PENDING)->where('provider', $gateway->name())->first();
        if (!$order instanceof Order) {
            $order = $billing->createOrder((int) $auth->id(), $page, $plan, $period, $gateway->name());
        }
        if ($this->online($gateway)) {
            // GCash, PayMaya and cards: the order page asks how to pay, and the form of the chosen way opens under it
            return Response::redirect('/dashboard/orders/' . $order->reference, 303);
        }
        return $this->checkout($gateway, $order, $this->editUrl($page, 'plan'));
    }

    /**
     * Photos for the account (12 for ₱100 a pack, 1 to the most an order may buy), paid like a plan: the same order page,
     * with GCash, PayMaya and card (or the provider's page). Body: packs, and page: the slug of the page it is bought
     * from, to come back to (optional).
     */
    public function storePhotos(ServerRequestInterface $request, Auth $auth, Billing $billing, PhotoAllowance $allowance, PaymentGateway $gateway): ResponseInterface
    {
        $body = Request::body($request);
        $slug = is_string($body['page'] ?? null) ? $body['page'] : '';
        $page = $slug !== '' ? $this->ownedPage($auth, $slug) : null;
        $packs = filter_var($body['packs'] ?? null, FILTER_VALIDATE_INT);
        if (!is_int($packs) || $packs < 1 || $packs > $allowance->maxPacks()) {
            throw new ValidationException(
                ['packs' => [sprintf('Choose how many photos to add: 1 to %d packs of %d.', $allowance->maxPacks(), $allowance->packPhotos())]],
                ['packs' => is_scalar($body['packs'] ?? null) ? (string) $body['packs'] : ''],
            );
        }

        // Clicking "buy" twice must not create two orders to pay.
        $pending = Order::query()->where('user_id', (int) $auth->id())->where('plan', Order::PHOTO_PACK)
            ->where('photos', $packs * $allowance->packPhotos())->where('status', Order::PENDING)->where('provider', $gateway->name());
        $order = ($page !== null ? $pending->where('page_id', $page->id) : $pending->whereNull('page_id'))->first();
        if (!$order instanceof Order) {
            $order = $billing->createPhotoOrder((int) $auth->id(), $page, $packs, $gateway->name());
        }
        if ($this->online($gateway)) {
            return Response::redirect('/dashboard/orders/' . $order->reference, 303); // the same page as a plan: choose how to pay
        }
        return $this->checkout($gateway, $order, $page instanceof Page ? $this->editUrl($page, 'photos') : '/dashboard');
    }

    public function show(string $reference, ServerRequestInterface $request, Auth $auth, Plans $plans, PaymentGateway $gateway): ResponseInterface
    {
        $order = $this->ownedOrder($auth, $reference);
        $page = $order->page_id !== null ? Page::query()->where('id', $order->page_id)->first() : null;
        return Screen::render('dashboard.order', [
            'order' => $order,
            'page' => $page instanceof Page ? $page : null,
            'plan' => $plans->get($order->plan),
            'hosted' => $gateway instanceof HostedGateway && !$this->online($gateway) && $order->provider === $gateway->name(),
            'online' => $this->online($gateway) && $order->provider === $gateway->name(),
            'go' => ($request->getQueryParams()['go'] ?? '') === '1', // just started an e-wallet payment: carry on to the wallet
            'attempt' => $this->liveAttempt($order),
            'methods' => Order::METHODS,
            'recurring' => $plans->months((string) $order->period) !== null,
            'renewal' => $order->subscription_id !== null,
            // for each way to pay: renews by itself (the provider charges again), or the customer pays each period
            'auto' => $gateway instanceof OnlineGateway
                ? [Order::GCASH => $gateway->canAutoCharge('gcash'), Order::PAYMAYA => $gateway->canAutoCharge('paymaya'), Order::CARD => $gateway->canAutoCharge('card')]
                : [],
            'canFakePay' => $gateway instanceof FakeGateway && $order->provider === 'fake' && $order->status === Order::PENDING,
            'instructions' => (string) config('saas.payments.manual_instructions'),
        ]);
    }

    /** Test checkout only (PAYMENTS_DRIVER=fake, never in production). */
    public function fakePay(string $reference, Auth $auth, PaymentGateway $gateway, Billing $billing): ResponseInterface
    {
        $order = $this->ownedOrder($auth, $reference);
        if (!$gateway instanceof FakeGateway || $order->provider !== 'fake') {
            throw new HttpException(404, 'Not Found');
        }
        $billing->markPaid($order, 'fake', 'fake_' . bin2hex(random_bytes(8)));

        $page = $order->page_id !== null ? Page::query()->where('id', $order->page_id)->first() : null;
        if ($order->isPhotoPack()) {
            return Screen::back(
                $page instanceof Page ? $this->editUrl($page, 'photos') : '/dashboard',
                sprintf('Payment received: %d more photos are ready to use on any of your pages.', $order->photos),
            );
        }
        return Screen::back($page instanceof Page ? $this->editUrl($page, 'plan') : '/dashboard', 'Payment received. Your page is live!');
    }

    /**
     * GCash or PayMaya: the customer entered their mobile number and chose one-time or subscribe. Starts the payment and
     * sends them to the order page, which takes them on to approve it in their wallet (a link, and the page follows it by itself).
     * Body: method (gcash or paymaya), phone, billing (subscription or one_time).
     */
    public function pay(string $reference, ServerRequestInterface $request, Auth $auth, PaymentGateway $gateway, Plans $plans): ResponseInterface
    {
        $order = $this->payableOrder($reference, $auth, $gateway);
        $body = Request::body($request);
        $method = (string) ($body['method'] ?? '');
        if (!in_array($method, [Order::GCASH, Order::PAYMAYA], true)) {
            throw new ValidationException(['method' => ['Choose GCash or PayMaya, or pay by card.']]);
        }
        $phone = self::mobile((string) ($body['phone'] ?? ''));
        if ($phone === null) {
            throw new ValidationException(['phone' => ['Enter your mobile number, like 0917 123 4567.']], ['phone' => (string) ($body['phone'] ?? ''), 'method' => $method]);
        }
        $billing = $this->billingFor($order, $method, (string) ($body['billing'] ?? ''), $plans);

        try {
            $this->start($order, $gateway, $method, $billing, $this->payer($auth, $phone));
        } catch (PaymentException $e) {
            return Screen::back('/dashboard/orders/' . $order->reference, $e->getMessage());
        }
        return Response::redirect('/dashboard/orders/' . $order->reference . '?go=1', 303);
    }

    /**
     * Card: starts the payment and hands the browser what it needs to send the card to the provider itself (JSON). The card
     * details never come here. A card always subscribes when the plan renews. Answers 502 with a message when the provider fails.
     */
    public function card(string $reference, Auth $auth, PaymentGateway $gateway, Plans $plans): ResponseInterface
    {
        $order = $this->payableOrder($reference, $auth, $gateway);
        $billing = $this->billingFor($order, Order::CARD, Order::SUBSCRIPTION, $plans);
        try {
            $started = $this->start($order, $gateway, Order::CARD, $billing, $this->payer($auth, ''));
        } catch (PaymentException $e) {
            return Response::json(['message' => $e->getMessage()], 502);
        }
        $user = $auth->user();
        return Response::json([
            'intent' => $started['ref'],
            'client_key' => $started['client_key'],
            'public_key' => $gateway instanceof OnlineGateway ? $gateway->publicKey() : '',
            'return_url' => rtrim((string) config('app.url'), '/') . '/dashboard/orders/' . $order->reference,
            'name' => $user instanceof \App\Models\User ? $user->name : '',
            'email' => $user instanceof \App\Models\User ? $user->email : '',
        ]);
    }

    /** JSON for the order page while it waits: asks the provider too, in case its webhook has not arrived. */
    public function status(string $reference, Auth $auth, PaymentGateway $gateway, Billing $billing): ResponseInterface
    {
        $order = $this->ownedOrder($auth, $reference);
        if ($order->status === Order::PENDING && $gateway instanceof OnlineGateway && $this->online($gateway) && $order->provider === $gateway->name()) {
            $attempts = PaymentAttempt::query()->withoutCache()->useWritePdo()->where('order_id', $order->id)->orderBy('id', 'desc')->limit(3)->get()->all();
            foreach ($attempts as $attempt) {
                $seen = $gateway->paymentStatus($attempt->provider_ref);
                if ($seen !== null && $seen['paid'] && $seen['amount'] === $order->amount && ($seen['currency'] ?? $order->currency) === $order->currency) {
                    $billing->markPaid($order, $gateway->name(), $attempt->provider_ref);
                    $order->refresh();
                    break;
                }
            }
        }
        return Response::json([
            'status' => $order->status,
            'paid' => $order->status === Order::PAID,
            'live' => $this->liveAttempt($order) !== null,
        ]);
    }

    /** Stops a page's subscription (and the provider's charging, when it charges by itself). The time already paid stays. */
    public function cancelSubscription(string $slug, Auth $auth, Subscriptions $subscriptions): ResponseInterface
    {
        $page = $this->ownedPage($auth, $slug);
        $subscription = $subscriptions->forPage($page->id);
        if ($subscription === null) {
            throw new HttpException(404, 'No subscription.');
        }
        return $this->stop($subscriptions, $subscription, $this->editUrl($page, 'plan'), $page);
    }

    /** The same, from the account's list of subscriptions on the dashboard. */
    public function cancelAccountSubscription(string $id, \Psr\Http\Message\ServerRequestInterface $request, Auth $auth, Subscriptions $subscriptions): ResponseInterface
    {
        $subscription = ctype_digit($id) ? Subscription::query()->withoutCache()->useWritePdo()->where('id', (int) $id)->where('user_id', (int) $auth->id())->where('status', Subscription::ACTIVE)->first() : null;
        if (!$subscription instanceof Subscription) {
            throw new HttpException(404, 'No subscription.');
        }
        $page = $subscription->page_id !== null ? Page::query()->where('id', $subscription->page_id)->first() : null;
        // the billing page asks to come back to itself
        $back = (string) (\Naluz\Http\Request::body($request)['back'] ?? '') === '/account/billing' ? '/account/billing' : '/dashboard';
        return $this->stop($subscriptions, $subscription, $back, $page instanceof Page ? $page : null);
    }

    private function stop(Subscriptions $subscriptions, Subscription $subscription, string $backTo, ?Page $page): ResponseInterface
    {
        try {
            $subscriptions->cancel($subscription);
        } catch (PaymentException $e) {
            return Screen::back($backTo, $e->getMessage());
        }
        $until = $page !== null && $page->expires_at !== null ? ' Your page stays online until ' . \App\Support\Time::show($page->expires_at) . '.' : '';
        return Screen::back($backTo, 'Subscription cancelled.' . $until);
    }

    /** Online payments (GCash, PayMaya, card) are how to pay when the provider has both its keys. */
    private function online(PaymentGateway $gateway): bool
    {
        return $gateway instanceof OnlineGateway && $gateway->onlineConfigured();
    }

    private function payableOrder(string $reference, Auth $auth, PaymentGateway $gateway): Order
    {
        $order = $this->ownedOrder($auth, $reference);
        if ($order->status !== Order::PENDING || !$this->online($gateway) || $order->provider !== $gateway->name()) {
            throw new HttpException(404, 'Not Found');
        }
        return $order;
    }

    /**
     * Starts the payment at the provider (as the first invoice of an automatic subscription when it can charge this way
     * again) and records it. A GCash payment cannot be charged again by itself, so it is only ever a manual subscription.
     *
     * @param array<string,string> $payer
     * @return array{ref:string,redirect_url:?string,client_key:string,subscription_ref:?string}
     * @throws PaymentException
     */
    private function start(Order $order, PaymentGateway $gateway, string $method, string $billing, array $payer): array
    {
        /** @var OnlineGateway $gateway */
        $returnUrl = rtrim((string) config('app.url'), '/') . '/dashboard/orders/' . $order->reference;
        $auto = $billing === Order::SUBSCRIPTION && $gateway->canAutoCharge($method);
        $started = $auto ? $gateway->startSubscription($order, $method, $payer, $returnUrl) : $gateway->startPayment($order, $method, $payer, $returnUrl);

        $attempt = new PaymentAttempt();
        $attempt->forceFill([
            'order_id' => $order->id, 'provider' => $gateway->name(), 'provider_ref' => $started['ref'], 'method' => $method, 'billing' => $billing,
            'redirect_url' => $started['redirect_url'], 'subscription_ref' => $started['subscription_ref'],
            'expires_at' => (new \DateTimeImmutable('+20 minutes', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
        ])->save();
        $order->forceFill(['method' => $method, 'billing' => $billing, 'provider_ref' => $started['ref']])->save();
        return $started;
    }

    /** @return array<string,string> */
    private function payer(Auth $auth, string $phone): array
    {
        $user = $auth->user();
        $name = $user instanceof \App\Models\User ? $user->name : '';
        $parts = preg_split('/\s+/', trim($name), 2) ?: [];
        return [
            'name' => $name,
            'first_name' => (string) ($parts[0] ?? ''),
            'last_name' => (string) ($parts[1] ?? ''),
            'email' => $user instanceof \App\Models\User ? $user->email : '',
            'phone' => $phone,
        ];
    }

    /** A Philippine mobile number as +639171234567, or null when it is not one. */
    public static function mobile(string $input): ?string
    {
        $digits = preg_replace('/[\s\-().]/', '', $input) ?? '';
        if (preg_match('/^(?:\+?63|0)?(9\d{9})$/', $digits, $m) !== 1) {
            return null;
        }
        return '+63' . $m[1];
    }

    private function billingFor(Order $order, string $method, string $asked, Plans $plans): string
    {
        if ($order->subscription_id !== null) {
            return Order::SUBSCRIPTION; // a renewal continues its subscription
        }
        if ($plans->months((string) $order->period) === null) {
            return Order::ONE_TIME; // bought once: nothing to renew
        }
        if ($method === Order::CARD) {
            return Order::SUBSCRIPTION; // a card always subscribes
        }
        // GCash and PayMaya: subscribe unless the customer chose a one-time payment
        return $asked === Order::ONE_TIME ? Order::ONE_TIME : Order::SUBSCRIPTION;
    }

    /** The newest payment of the order that is still open. */
    private function liveAttempt(Order $order): ?PaymentAttempt
    {
        $attempt = PaymentAttempt::query()->withoutCache()->useWritePdo()->where('order_id', $order->id)->orderBy('id', 'desc')->first();
        return $attempt instanceof PaymentAttempt && !$attempt->expired() ? $attempt : null;
    }

    /** Sends the customer to pay; when the provider cannot open a checkout the order stays pending and they hear why. */
    private function checkout(PaymentGateway $gateway, Order $order, string $backTo): ResponseInterface
    {
        try {
            return Response::redirect($gateway->checkoutUrl($order), 303);
        } catch (PaymentException $e) {
            return Screen::back($backTo, $e->getMessage());
        }
    }

    private function ownedOrder(Auth $auth, string $reference): Order
    {
        $order = Order::query()->where('reference', $reference)->where('user_id', (int) $auth->id())->first();
        if (!$order instanceof Order) {
            throw new HttpException(404, 'Order not found.');
        }
        return $order;
    }
}

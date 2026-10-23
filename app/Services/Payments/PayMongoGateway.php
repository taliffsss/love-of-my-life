<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\ProviderRef;
use GuzzleHttp\ClientInterface;

/**
 * PayMongo (https://developers.paymongo.com), two ways:
 *  - on our own order page (with the public key as well): GCash, PayMaya, QR Ph and cards through payment intents, and cards and
 *    Maya as subscriptions PayMongo keeps charging (PAYMONGO_SUBSCRIPTIONS, which PayMongo must enable on the account);
 *  - without the public key, PayMongo's hosted Checkout Session page.
 *
 * Setup: PAYMENTS_DRIVER=paymongo (or auto), PAYMONGO_SECRET_KEY (sk_test_… / sk_live_…), PAYMONGO_PUBLIC_KEY, and a
 * webhook in the PayMongo dashboard for `payment.paid` and `checkout_session.payment.paid` at
 * <APP_URL>/api/webhooks/paymongo; its signing secret goes in PAYMONGO_WEBHOOK_SECRET.
 */
final class PayMongoGateway extends HostedGateway implements OnlineGateway
{
    private const API = 'https://api.paymongo.com/v1';

    /** @param list<string> $methods payment_method_types offered on the checkout */
    public function __construct(
        private readonly string $secretKey,
        private readonly string $webhookSecret,
        private readonly array $methods,
        string $appUrl,
        ?ClientInterface $http = null,
        private readonly string $publicKey = '',
        private readonly bool $subscriptionsEnabled = false,
    ) {
        parent::__construct($appUrl, $http);
    }

    public function name(): string
    {
        return 'paymongo';
    }

    public function configured(): bool
    {
        return $this->secretKey !== '';
    }

    /** Cards, GCash and PayMaya on our own page need the secret key (payment intents, subscriptions) and the public key (payment methods). */
    public function onlineConfigured(): bool
    {
        return $this->secretKey !== '' && $this->publicKey !== '';
    }

    public function publicKey(): string
    {
        return $this->publicKey;
    }

    /** PayMongo Subscriptions take cards and Maya (docs.paymongo.com); GCash cannot be charged again by itself. They also have to be enabled on the account. */
    public function canAutoCharge(string $method): bool
    {
        return $this->subscriptionsEnabled && in_array($method, [self::CARD, self::PAYMAYA], true);
    }

    public function startPayment(Order $order, string $method, array $payer, string $returnUrl): array
    {
        $this->assertOnline($method);
        $intent = $this->request('POST', self::API . '/payment_intents', [
            'headers' => $this->headers($this->secretKey),
            'body' => json_encode(['data' => ['attributes' => array_filter([
                'amount' => $order->amount,
                'currency' => $order->currency,
                'payment_method_allowed' => [$method],
                'payment_method_options' => $method === self::CARD ? ['card' => ['request_three_d_secure' => 'any']] : null,
                'description' => 'Order ' . $order->reference,
                'metadata' => ['order_reference' => $order->reference],
            ], static fn ($v): bool => $v !== null)]], JSON_THROW_ON_ERROR),
        ]);
        $id = (string) ($intent['data']['id'] ?? '');
        $clientKey = (string) ($intent['data']['attributes']['client_key'] ?? '');
        if ($id === '' || $clientKey === '') {
            throw $this->failed();
        }

        return $this->finish($id, $clientKey, $method, $payer, $returnUrl, null);
    }

    public function startSubscription(Order $order, string $method, array $payer, string $returnUrl): array
    {
        $this->assertOnline($method);
        if (!$this->canAutoCharge($method)) {
            throw new PaymentException('This payment method cannot renew by itself.');
        }
        $customer = $this->customerRef((int) $order->user_id, $payer);
        $plan = $this->planRef($order);
        $subscription = $this->request('POST', self::API . '/subscriptions', [
            'headers' => $this->headers($this->secretKey),
            'body' => json_encode(['data' => ['attributes' => ['customer_id' => $customer, 'plan_id' => $plan]]], JSON_THROW_ON_ERROR),
        ]);
        $subscriptionRef = (string) ($subscription['data']['id'] ?? '');
        // the first invoice has a payment intent: the customer pays it now, which also keeps their card or Maya for later charges
        $intentRef = (string) ($subscription['data']['attributes']['latest_invoice']['payment_intent']['id'] ?? '');
        if ($subscriptionRef === '' || $intentRef === '') {
            throw $this->failed();
        }
        $intent = $this->request('GET', self::API . '/payment_intents/' . rawurlencode($intentRef), ['headers' => $this->headers($this->secretKey)]);
        $clientKey = (string) ($intent['data']['attributes']['client_key'] ?? '');
        if ($clientKey === '') {
            throw $this->failed();
        }

        return $this->finish($intentRef, $clientKey, $method, $payer, $returnUrl, $subscriptionRef);
    }

    public function qrImage(string $ref): ?string
    {
        try {
            $intent = $this->request('GET', self::API . '/payment_intents/' . rawurlencode($ref), ['headers' => $this->headers($this->secretKey)]);
        } catch (PaymentException) {
            return null;
        }
        return self::codeImage($intent);
    }

    /** The QR image of a payment intent response, as a data: URI (PayMongo sends it as a base64 PNG); null when there is none. @param array<mixed> $intent */
    private static function codeImage(array $intent): ?string
    {
        $attributes = (array) ($intent['data']['attributes'] ?? []);
        if (($attributes['status'] ?? 'awaiting_next_action') !== 'awaiting_next_action') {
            return null;
        }
        $image = (string) ($attributes['next_action']['code']['image_url'] ?? '');
        if (str_starts_with($image, 'data:image/png;base64,')) {
            $image = substr($image, 22);
        }
        return $image !== '' && preg_match('~^[A-Za-z0-9+/]+={0,2}$~', $image) === 1 ? 'data:image/png;base64,' . $image : null;
    }

    public function paymentStatus(string $ref): ?array
    {
        try {
            $intent = $this->request('GET', self::API . '/payment_intents/' . rawurlencode($ref), ['headers' => $this->headers($this->secretKey)]);
        } catch (PaymentException) {
            return null;
        }
        $attributes = (array) ($intent['data']['attributes'] ?? []);
        if (!isset($attributes['status'])) {
            return null;
        }
        return [
            'paid' => $attributes['status'] === 'succeeded',
            'amount' => isset($attributes['amount']) ? (int) $attributes['amount'] : null,
            'currency' => isset($attributes['currency']) ? strtoupper((string) $attributes['currency']) : null,
        ];
    }

    public function subscriptionState(string $subscriptionRef): ?array
    {
        try {
            $data = $this->request('GET', self::API . '/subscriptions/' . rawurlencode($subscriptionRef), ['headers' => $this->headers($this->secretKey)]);
        } catch (PaymentException) {
            return null;
        }
        $attributes = (array) ($data['data']['attributes'] ?? []);
        if (!isset($attributes['status'])) {
            return null;
        }
        $invoice = (array) ($attributes['latest_invoice'] ?? []);
        return [
            'status' => (string) $attributes['status'],
            'invoice_ref' => isset($invoice['id']) ? (string) $invoice['id'] : null,
            'invoice_paid' => ($invoice['status'] ?? '') === 'paid',
            'amount' => isset($invoice['amount']) ? (int) $invoice['amount'] : null,
        ];
    }

    public function cancelSubscription(string $subscriptionRef): void
    {
        $this->request('DELETE', self::API . '/subscriptions/' . rawurlencode($subscriptionRef), ['headers' => $this->headers($this->secretKey)]);
    }

    /**
     * An e-wallet is attached now (PayMongo makes the payment method with the payer's details and answers with the page
     * where the customer approves it); a card is finished by the browser, so only the client key goes back.
     *
     * @param array<string,string> $payer
     * @return array{ref:string,redirect_url:?string,client_key:string,subscription_ref:?string}
     */
    private function finish(string $ref, string $clientKey, string $method, array $payer, string $returnUrl, ?string $subscriptionRef): array
    {
        if ($method === self::CARD) {
            return ['ref' => $ref, 'redirect_url' => null, 'client_key' => $clientKey, 'subscription_ref' => $subscriptionRef];
        }
        $made = $this->request('POST', self::API . '/payment_methods', [
            'headers' => $this->headers($this->publicKey),
            'body' => json_encode(['data' => ['attributes' => [
                'type' => $method,
                'billing' => array_filter(['name' => $payer['name'] ?? '', 'email' => $payer['email'] ?? '', 'phone' => $payer['phone'] ?? ''], static fn ($v): bool => $v !== ''),
            ]]], JSON_THROW_ON_ERROR),
        ]);
        $methodRef = (string) ($made['data']['id'] ?? '');
        if ($methodRef === '') {
            throw $this->failed();
        }
        $attached = $this->request('POST', self::API . '/payment_intents/' . rawurlencode($ref) . '/attach', [
            'headers' => $this->headers($this->publicKey),
            'body' => json_encode(['data' => ['attributes' => ['payment_method' => $methodRef, 'client_key' => $clientKey, 'return_url' => $returnUrl]]], JSON_THROW_ON_ERROR),
        ]);
        if ($method === self::QRPH) {
            // nothing to open: the customer scans the code, which qrImage() reads back from the payment
            if (self::codeImage($attached) === null) {
                throw $this->failed();
            }
            return ['ref' => $ref, 'redirect_url' => null, 'client_key' => $clientKey, 'subscription_ref' => $subscriptionRef];
        }
        $url = (string) ($attached['data']['attributes']['next_action']['redirect']['url'] ?? '');
        if (!str_starts_with($url, 'https://')) {
            throw $this->failed();
        }
        return ['ref' => $ref, 'redirect_url' => $url, 'client_key' => $clientKey, 'subscription_ref' => $subscriptionRef];
    }

    /** One PayMongo customer per user, made the first time and reused. @param array<string,string> $payer */
    private function customerRef(int $userId, array $payer): string
    {
        $known = ProviderRef::lookup($this->name(), 'customer', 'user:' . $userId);
        if ($known !== null) {
            return $known;
        }
        $made = $this->request('POST', self::API . '/customers', [
            'headers' => $this->headers($this->secretKey),
            'body' => json_encode(['data' => ['attributes' => array_filter([
                'first_name' => $payer['first_name'] ?? '',
                'last_name' => $payer['last_name'] ?? '',
                'email' => $payer['email'] ?? '',
                'phone' => $payer['phone'] ?? '',
            ], static fn ($v): bool => $v !== '')]], JSON_THROW_ON_ERROR),
        ]);
        $ref = (string) ($made['data']['id'] ?? '');
        if ($ref === '') {
            throw $this->failed();
        }
        ProviderRef::remember($this->name(), 'customer', 'user:' . $userId, $ref);
        return $ref;
    }

    /** One PayMongo plan per price (plan, period and amount), made the first time and reused. */
    private function planRef(Order $order): string
    {
        $key = $order->plan . ':' . $order->period . ':' . $order->amount;
        $known = ProviderRef::lookup($this->name(), 'plan', $key);
        if ($known !== null) {
            return $known;
        }
        $every = ['month' => 'monthly', 'year' => 'yearly'][(string) $order->period] ?? null;
        if ($every === null) {
            throw new PaymentException('This plan does not renew.');
        }
        $made = $this->request('POST', self::API . '/subscriptions/plans', [
            'headers' => $this->headers($this->secretKey),
            'body' => json_encode(['data' => ['attributes' => [
                'name' => ucfirst($order->plan) . ' (' . $every . ')',
                'description' => ucfirst($order->plan) . ' plan, billed ' . $every,
                'amount' => $order->amount,
                'currency' => $order->currency,
                'interval' => $every,
                'interval_count' => 1,
            ]]], JSON_THROW_ON_ERROR),
        ]);
        $ref = (string) ($made['data']['id'] ?? '');
        if ($ref === '') {
            throw $this->failed();
        }
        ProviderRef::remember($this->name(), 'plan', $key, $ref);
        return $ref;
    }

    private function assertOnline(string $method): void
    {
        if (!$this->onlineConfigured() || !in_array($method, [self::GCASH, self::PAYMAYA, self::CARD, self::QRPH], true)) {
            throw new PaymentException('Online payment is not available right now. Please try again later.');
        }
    }

    private function failed(): PaymentException
    {
        return new PaymentException('The payment provider could not start the payment. Please try again in a moment.');
    }

    /** @return array<string,string> */
    private function headers(string $key): array
    {
        return ['Authorization' => 'Basic ' . base64_encode($key . ':'), 'Content-Type' => 'application/json', 'Accept' => 'application/json'];
    }

    public function webhookConfigured(): bool
    {
        return $this->webhookSecret !== '';
    }

    protected function createCheckout(Order $order): array
    {
        $data = $this->request('POST', self::API . '/checkout_sessions', [
            'headers' => ['Authorization' => 'Basic ' . base64_encode($this->secretKey . ':'), 'Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'body' => json_encode(['data' => ['attributes' => [
                'line_items' => [[
                    'currency' => $order->currency,
                    'amount' => $order->amount,
                    'name' => $this->description($order),
                    'quantity' => 1,
                ]],
                'payment_method_types' => $this->methods,
                'reference_number' => $order->reference,
                'description' => 'Order ' . $order->reference,
                'metadata' => ['order_reference' => $order->reference],
                'success_url' => $this->returnUrl($order),
                'cancel_url' => $this->returnUrl($order),
                'send_email_receipt' => false,
            ]]], JSON_THROW_ON_ERROR),
        ]);
        $id = (string) ($data['data']['id'] ?? '');
        $url = (string) ($data['data']['attributes']['checkout_url'] ?? '');
        if ($id === '' || !str_starts_with($url, 'https://')) {
            throw new PaymentException('The payment provider could not start the checkout. Please try again in a moment.');
        }
        return ['id' => $id, 'url' => $url];
    }

    /**
     * Checks the `Paymongo-Signature` header (`t=<timestamp>,te=<test signature>,li=<live signature>`: HMAC-SHA256 of
     * "<timestamp>.<raw body>" with the webhook secret; the live signature for live events, the test one otherwise) and
     * reads the event. Returns null for events we do not act on.
     *
     * @throws InvalidWebhook
     */
    public function parseWebhook(string $rawBody, string $signatureHeader): ?WebhookNotice
    {
        if ($this->webhookSecret === '') {
            throw new InvalidWebhook('The PayMongo webhook secret is not set.');
        }
        $parts = [];
        foreach (explode(',', $signatureHeader) as $piece) {
            [$key, $value] = array_pad(explode('=', trim($piece), 2), 2, '');
            $parts[$key] = $value;
        }
        $event = json_decode($rawBody, true);
        if (!is_array($event) || ($parts['t'] ?? '') === '') {
            throw new InvalidWebhook('Unreadable webhook.');
        }
        $live = ($event['data']['attributes']['livemode'] ?? false) === true;
        $given = (string) ($parts[$live ? 'li' : 'te'] ?? '');
        $expected = hash_hmac('sha256', $parts['t'] . '.' . $rawBody, $this->webhookSecret);
        if ($given === '' || !hash_equals($expected, $given)) {
            throw new InvalidWebhook('Bad signature.');
        }

        $attributes = (array) ($event['data']['attributes'] ?? []);
        if (($attributes['type'] ?? '') === 'payment.paid') {
            // a GCash, PayMaya or card payment: it says which payment intent it settles, and that is what we stored
            $payment = (array) ($attributes['data']['attributes'] ?? []);
            $intent = (string) ($payment['payment_intent_id'] ?? '');
            if ($intent === '' || ($payment['status'] ?? 'paid') !== 'paid') {
                return null;
            }
            return new WebhookNotice(
                WebhookNotice::PAID,
                '',
                $intent,
                isset($payment['amount']) ? (int) $payment['amount'] : null,
                isset($payment['currency']) ? strtoupper((string) $payment['currency']) : null,
            );
        }
        if (($attributes['type'] ?? '') !== 'checkout_session.payment.paid') {
            return null;
        }
        $session = (array) ($attributes['data'] ?? []);
        $payment = (array) (((array) ($session['attributes']['payments'] ?? []))[0]['attributes'] ?? []);
        if (($payment['status'] ?? 'paid') !== 'paid') {
            return null;
        }
        $reference = (string) ($session['attributes']['reference_number'] ?? ($session['attributes']['metadata']['order_reference'] ?? ''));
        $id = (string) ($session['id'] ?? '');
        if ($reference === '' || $id === '') {
            return null;
        }
        return new WebhookNotice(
            WebhookNotice::PAID,
            $reference,
            $id,
            isset($payment['amount']) ? (int) $payment['amount'] : null,
            isset($payment['currency']) ? strtoupper((string) $payment['currency']) : null,
        );
    }
}

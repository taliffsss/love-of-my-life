<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Order;
use GuzzleHttp\ClientInterface;

/**
 * PayMongo Checkout Sessions (GCash, Maya, cards, GrabPay...). https://developers.paymongo.com
 *
 * Setup: PAYMENTS_DRIVER=paymongo, PAYMONGO_SECRET_KEY (sk_test_… / sk_live_…), and a webhook in the PayMongo dashboard
 * for the event `checkout_session.payment.paid` at <APP_URL>/api/webhooks/paymongo; its signing secret goes in
 * PAYMONGO_WEBHOOK_SECRET.
 */
final class PayMongoGateway extends HostedGateway
{
    private const API = 'https://api.paymongo.com/v1';

    /** @param list<string> $methods payment_method_types offered on the checkout */
    public function __construct(
        private readonly string $secretKey,
        private readonly string $webhookSecret,
        private readonly array $methods,
        string $appUrl,
        ?ClientInterface $http = null,
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

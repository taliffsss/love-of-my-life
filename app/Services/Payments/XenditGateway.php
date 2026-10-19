<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\User;
use GuzzleHttp\ClientInterface;

/**
 * Xendit Invoices (GCash, Maya, cards, bank and over-the-counter). https://docs.xendit.co/docs/invoice-api
 *
 * Setup: PAYMENTS_DRIVER=xendit, XENDIT_SECRET_KEY (xnd_development_… / xnd_production_…), and in the Xendit dashboard
 * (Settings, Webhooks) the Invoices paid and Invoices expired URLs set to <APP_URL>/api/webhooks/xendit; the dashboard's
 * verification token goes in XENDIT_CALLBACK_TOKEN. Xendit works in whole pesos, not centavos.
 */
final class XenditGateway extends HostedGateway
{
    private const API = 'https://api.xendit.co/v2/invoices';
    private const INVOICE_SECONDS = 86400;

    public function __construct(
        private readonly string $secretKey,
        private readonly string $callbackToken,
        string $appUrl,
        ?ClientInterface $http = null,
    ) {
        parent::__construct($appUrl, $http);
    }

    public function name(): string
    {
        return 'xendit';
    }

    public function configured(): bool
    {
        return $this->secretKey !== '';
    }

    protected function createCheckout(Order $order): array
    {
        $email = User::query()->find($order->user_id)?->email;
        $body = [
            'external_id' => $order->reference,
            'amount' => $order->amount / 100,
            'currency' => $order->currency,
            'description' => $this->description($order) . ' (' . $order->reference . ')',
            'invoice_duration' => self::INVOICE_SECONDS,
            'success_redirect_url' => $this->returnUrl($order),
            'failure_redirect_url' => $this->returnUrl($order),
        ];
        if (is_string($email) && $email !== '') {
            $body['payer_email'] = $email;
        }
        $data = $this->request('POST', self::API, [
            'headers' => ['Authorization' => 'Basic ' . base64_encode($this->secretKey . ':'), 'Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'body' => json_encode($body, JSON_THROW_ON_ERROR),
        ]);
        $id = (string) ($data['id'] ?? '');
        $url = (string) ($data['invoice_url'] ?? '');
        if ($id === '' || !str_starts_with($url, 'https://')) {
            throw new PaymentException('The payment provider could not start the checkout. Please try again in a moment.');
        }
        return ['id' => $id, 'url' => $url];
    }

    /**
     * Checks the `x-callback-token` header against the verification token and reads the invoice event. Returns null for
     * statuses we do not act on (PENDING).
     *
     * @throws InvalidWebhook
     */
    public function parseWebhook(string $rawBody, string $tokenHeader): ?WebhookNotice
    {
        if ($this->callbackToken === '' || $tokenHeader === '' || !hash_equals($this->callbackToken, $tokenHeader)) {
            throw new InvalidWebhook('Bad callback token.');
        }
        $invoice = json_decode($rawBody, true);
        if (!is_array($invoice)) {
            throw new InvalidWebhook('Unreadable webhook.');
        }
        $reference = (string) ($invoice['external_id'] ?? '');
        $id = (string) ($invoice['id'] ?? '');
        if ($reference === '' || $id === '') {
            return null;
        }
        $status = strtoupper((string) ($invoice['status'] ?? ''));
        $currency = isset($invoice['currency']) ? strtoupper((string) $invoice['currency']) : null;
        if ($status === 'PAID' || $status === 'SETTLED') {
            $pesos = $invoice['paid_amount'] ?? $invoice['amount'] ?? null;
            return new WebhookNotice(WebhookNotice::PAID, $reference, $id, is_numeric($pesos) ? (int) round((float) $pesos * 100) : null, $currency);
        }
        if ($status === 'EXPIRED') {
            return new WebhookNotice(WebhookNotice::EXPIRED, $reference, $id, null, $currency);
        }
        return null;
    }
}

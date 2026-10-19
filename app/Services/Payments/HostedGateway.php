<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Order;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

/**
 * A payment provider with a hosted checkout page (PayMongo, Xendit). The order is created first, then a checkout is
 * opened for it at the provider and the customer is sent there; the provider tells us it was paid by a webhook
 * (App\Http\Controllers\WebhooksController), never by the customer's return to the site.
 *
 * A pending order keeps its checkout (`checkout_url`, and the provider's id in `provider_ref`) for a few hours, so
 * clicking "buy" again returns to the same checkout.
 */
abstract class HostedGateway implements PaymentGateway
{
    /** How long a checkout already opened for an order is offered again; the providers keep theirs open longer. */
    private const REUSE_SECONDS = 6 * 3600;

    public function __construct(protected readonly string $appUrl, private readonly ?ClientInterface $http = null)
    {
    }

    /** Whether the credentials needed to talk to the provider are set. */
    abstract public function configured(): bool;

    /** Opens a checkout for the order. @return array{id:string,url:string} @throws PaymentException */
    abstract protected function createCheckout(Order $order): array;

    final public function checkoutUrl(Order $order): string
    {
        $url = (string) $order->checkout_url;
        $fresh = $order->updated_at !== null && time() - $order->updated_at->getTimestamp() < self::REUSE_SECONDS;
        if ($url !== '' && $fresh && (string) $order->provider_ref !== '' && $order->provider === $this->name()) {
            return $url;
        }
        if (!$this->configured()) {
            throw new PaymentException('Online payment is not available right now. Please try again later.');
        }

        $checkout = $this->createCheckout($order);
        $order->forceFill(['provider' => $this->name(), 'provider_ref' => $checkout['id'], 'checkout_url' => $checkout['url']])->save();
        return $checkout['url'];
    }

    protected function returnUrl(Order $order): string
    {
        return rtrim($this->appUrl, '/') . '/dashboard/orders/' . $order->reference;
    }

    protected function description(Order $order): string
    {
        return $order->isPhotoPack() ? $order->photos . ' more photos' : ucfirst($order->plan) . ' plan';
    }

    /** One JSON call to the provider. @return array<string,mixed> @throws PaymentException */
    protected function request(string $method, string $url, array $options): array
    {
        try {
            $response = ($this->http ?? new Client(['timeout' => 10, 'connect_timeout' => 5, 'http_errors' => false]))->request($method, $url, $options);
        } catch (\Throwable $e) {
            // class only: a transport error's message can carry the request URL
            logger()->error('[payments] {gateway} request failed: {class}', ['gateway' => $this->name(), 'class' => $e::class]);
            throw new PaymentException('We could not reach the payment provider. Please try again in a moment.');
        }
        $data = json_decode((string) $response->getBody(), true);
        if ($response->getStatusCode() >= 400 || !is_array($data)) {
            logger()->error('[payments] {gateway} answered {status}', ['gateway' => $this->name(), 'status' => $response->getStatusCode()]);
            throw new PaymentException('The payment provider could not start the checkout. Please try again in a moment.');
        }
        return $data;
    }
}

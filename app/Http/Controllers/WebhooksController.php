<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\Billing;
use App\Services\Payments\HostedGateway;
use App\Services\Payments\InvalidWebhook;
use App\Services\Payments\PayMongoGateway;
use App\Services\Payments\WebhookNotice;
use App\Services\Payments\XenditGateway;
use Naluz\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Where PayMongo and Xendit tell us an order was paid. Nothing here trusts the request until the provider's signature or
 * token checks out; then the order is found by our reference AND the provider's checkout id (the one we stored when the
 * checkout was opened) and the amount must match, so a payment cannot be credited to another order.
 *
 * Answers: 400 for a request that is not from the provider (it retries nothing useful), 200 for anything understood,
 * including events we ignore or orders we do not recognise (the provider must not keep retrying those). A failure while
 * crediting an order surfaces as a 500, so the provider retries; crediting twice is harmless (Billing::markPaid).
 */
final class WebhooksController
{
    public function paymongo(ServerRequestInterface $request, PayMongoGateway $gateway, Billing $billing): ResponseInterface
    {
        if (!$gateway->webhookConfigured()) {
            return Response::json(['error' => 'not configured'], 404);
        }
        try {
            $notice = $gateway->parseWebhook((string) $request->getBody(), $request->getHeaderLine('Paymongo-Signature'));
        } catch (InvalidWebhook) {
            return Response::json(['error' => 'invalid'], 400);
        }
        return $this->settle($notice, $gateway, $billing);
    }

    public function xendit(ServerRequestInterface $request, XenditGateway $gateway, Billing $billing): ResponseInterface
    {
        try {
            $notice = $gateway->parseWebhook((string) $request->getBody(), $request->getHeaderLine('x-callback-token'));
        } catch (InvalidWebhook) {
            return Response::json(['error' => 'invalid'], 400);
        }
        return $this->settle($notice, $gateway, $billing);
    }

    private function settle(?WebhookNotice $notice, HostedGateway $gateway, Billing $billing): ResponseInterface
    {
        if ($notice === null) {
            return Response::json(['ok' => true, 'ignored' => true]);
        }
        $provider = $gateway->name();
        if ($notice->reference === '') {
            // a payment names only the provider's id for it: every payment started for an order is on record, so a payment of
            // an earlier one (the customer changed method, then approved the first) still reaches its order
            $attempt = PaymentAttempt::withProviderRef($provider, $notice->providerRef);
            $order = $attempt instanceof PaymentAttempt ? Order::query()->withoutCache()->useWritePdo()->find($attempt->order_id) : null;
        } else {
            $order = Order::query()->withoutCache()->useWritePdo()->where('reference', $notice->reference)->where('provider', $provider)->first();
        }
        $known = $order instanceof Order && ($notice->reference === '' || (string) $order->provider_ref === $notice->providerRef);
        if (!$known) {
            logger()->warning('[payments] {provider} event for an unknown order {reference}', ['provider' => $provider, 'reference' => $notice->reference]);
            return Response::json(['ok' => true, 'ignored' => true]);
        }

        if ($notice->kind === WebhookNotice::EXPIRED) {
            $billing->markCancelled($order);
            return Response::json(['ok' => true]);
        }

        if ($notice->amount !== $order->amount || ($notice->currency !== null && $notice->currency !== $order->currency)) {
            logger()->error('[payments] {provider} paid {amount} {currency} for order {reference} ({expected} expected): not activated, check it by hand', [
                'provider' => $provider, 'amount' => $notice->amount, 'currency' => $notice->currency, 'reference' => $order->reference, 'expected' => $order->amount,
            ]);
            return Response::json(['ok' => true, 'ignored' => true]);
        }
        if ($order->status === Order::CANCELLED) {
            logger()->warning('[payments] order {reference} was paid after it was cancelled: refund it or grant it by hand', ['reference' => $order->reference]);
            return Response::json(['ok' => true, 'ignored' => true]);
        }

        $billing->markPaid($order, $provider, $notice->providerRef);
        return Response::json(['ok' => true]);
    }
}

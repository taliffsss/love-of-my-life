<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Order;

/**
 * A provider that takes GCash, PayMaya and cards on our own order page (no hosted checkout page). The customer's card
 * number never reaches this server: the browser sends it straight to the provider with the public key.
 *
 * `$payer` is ['name', 'first_name', 'last_name', 'email', 'phone'] (phone like +639171234567, empty for a card).
 */
interface OnlineGateway extends PaymentGateway
{
    public const GCASH = 'gcash';
    public const PAYMAYA = 'paymaya';
    public const CARD = 'card';

    /** Whether the keys for this are set (secret and public). */
    public function onlineConfigured(): bool;

    /** The key the browser uses to send a card to the provider. Safe to show the page. */
    public function publicKey(): string;

    /** Whether the provider can charge a customer again each period by itself, for this method (GCash cannot). */
    public function canAutoCharge(string $method): bool;

    /**
     * Starts one payment. An e-wallet is attached on the spot and comes back with `redirect_url`, where the customer approves
     * it. A card comes back with `client_key` instead: the browser finishes it with the card details.
     *
     * @param array<string,string> $payer
     * @return array{ref:string,redirect_url:?string,client_key:string,subscription_ref:?string}
     * @throws PaymentException
     */
    public function startPayment(Order $order, string $method, array $payer, string $returnUrl): array;

    /**
     * Same, as the first payment of a subscription the provider will keep charging.
     *
     * @param array<string,string> $payer
     * @return array{ref:string,redirect_url:?string,client_key:string,subscription_ref:?string}
     * @throws PaymentException
     */
    public function startSubscription(Order $order, string $method, array $payer, string $returnUrl): array;

    /**
     * What the provider says about a payment (for when its webhook has not arrived).
     *
     * @return array{paid:bool,amount:?int,currency:?string}|null null when it cannot say
     */
    public function paymentStatus(string $ref): ?array;

    /**
     * The provider's subscription.
     *
     * @return array{status:string,invoice_ref:?string,invoice_paid:bool,amount:?int}|null null when it cannot say
     */
    public function subscriptionState(string $subscriptionRef): ?array;

    /** Stops the provider charging a subscription. @throws PaymentException when it could not be stopped */
    public function cancelSubscription(string $subscriptionRef): void;
}

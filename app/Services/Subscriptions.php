<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Page;
use App\Models\PaymentAttempt;
use App\Models\Subscription;
use App\Services\Payments\OnlineGateway;
use App\Services\Payments\PaymentException;
use App\Services\Payments\PaymentGateway;
use Naluz\Database\Connection;

/**
 * Plans that keep renewing. A subscription is made by the first paid order that was a subscription (a one-time order never
 * makes one), in one of two modes:
 *
 *  - `auto`: the payment provider charges the customer each period (PayMongo Subscriptions: cards and Maya, once PayMongo
 *    has enabled them on the account). Nothing is made here; this app only notices the invoices the provider paid
 *    (syncAuto) and extends the page for each, and stops the provider when the customer cancels.
 *  - `manual`: nothing is charged for the customer. Three days before the page runs out a renewal order is made and the
 *    dashboard asks them to pay it (a GCash payment, for one, cannot be charged again by itself).
 *
 * The page's own expiry is the clock: paying a renewal extends the page as any payment does. A subscription ends when its
 * page is deleted or has moved to a higher plan, and an automatic one is then stopped at the provider too.
 */
final class Subscriptions
{
    /** How many days before a page expires a manual subscription's renewal order is made. */
    public const LEAD_DAYS = 3;

    public function __construct(private readonly Connection $db, private readonly Plans $plans, private readonly Billing $billing)
    {
    }

    /** The subscription that is running for a page, if any. */
    public function forPage(int $pageId): ?Subscription
    {
        $sub = Subscription::query()->withoutCache()->useWritePdo()->where('page_id', $pageId)->where('status', Subscription::ACTIVE)->first();
        return $sub instanceof Subscription ? $sub : null;
    }

    /** @return list<Subscription> every running subscription of an account */
    public function forUser(int $userId): array
    {
        return Subscription::query()->withoutCache()->useWritePdo()->where('user_id', $userId)->where('status', Subscription::ACTIVE)->orderBy('id')->get()->all();
    }

    /** Called when an order is paid: a subscription order starts (or updates) the page's subscription. */
    public function recordPaid(Order $order): void
    {
        if ($order->billing !== Order::SUBSCRIPTION || $order->page_id === null || $order->isPhotoPack()) {
            return;
        }
        $sub = $order->subscription_id !== null ? Subscription::query()->withoutCache()->useWritePdo()->find($order->subscription_id) : null;
        $sub = $sub instanceof Subscription ? $sub : $this->forPage((int) $order->page_id);
        if (!$sub instanceof Subscription) {
            $sub = new Subscription();
            $sub->forceFill(['user_id' => $order->user_id, 'page_id' => $order->page_id]);
        }

        // the provider's own subscription, when this payment was the first invoice of one
        $attempt = (string) $order->provider_ref !== '' ? PaymentAttempt::withProviderRef($order->provider, (string) $order->provider_ref) : null;
        $attempt = $attempt instanceof PaymentAttempt && $attempt->order_id === $order->id ? $attempt : null;
        $providerRef = $attempt instanceof PaymentAttempt && (string) $attempt->subscription_ref !== '' ? (string) $attempt->subscription_ref : null;
        $invoice = null;
        if ($providerRef !== null) {
            $state = $this->gateway()?->subscriptionState($providerRef);
            $invoice = $state['invoice_ref'] ?? null; // already paid by this payment: it must not be counted again
        }
        // a period the provider charged (no attempt of ours) keeps the automatic subscription as it is
        $keepAuto = $sub->id !== null && $sub->mode === 'auto' && $providerRef === null;
        $sub->forceFill($keepAuto ? [] : [
            'mode' => $providerRef !== null ? 'auto' : 'manual',
            'provider_ref' => $providerRef,
            'last_invoice_ref' => $invoice,
        ]);
        $sub->forceFill([
            'plan' => $order->plan,
            'period' => (string) $order->period,
            'amount' => $order->amount,
            'currency' => $order->currency,
            'method' => (string) $order->method,
            'provider' => $order->provider,
            'status' => Subscription::ACTIVE,
            'cancelled_at' => null,
        ])->save();
        if ($order->subscription_id !== $sub->id) {
            $this->db->table('orders')->where('id', '=', $order->id)->update(['subscription_id' => $sub->id]);
        }
    }

    /**
     * Stops a subscription: no more renewal orders and, for an automatic one, no more charges. The time already paid stays.
     *
     * @throws PaymentException when the provider could not be told to stop charging (the subscription is left running)
     */
    public function cancel(Subscription $sub, ?\DateTimeImmutable $now = null): void
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if ($sub->mode === 'auto' && (string) $sub->provider_ref !== '') {
            $gateway = $this->gateway();
            if ($gateway === null) {
                throw new PaymentException('We could not reach the payment provider to stop the subscription. Please try again in a moment.');
            }
            $gateway->cancelSubscription((string) $sub->provider_ref);
        }
        $sub->forceFill(['status' => Subscription::CANCELLED, 'cancelled_at' => $now->format('Y-m-d H:i:s')])->save();
        // a renewal that was not paid yet is no longer wanted
        $this->db->table('orders')->where('subscription_id', '=', $sub->id)->where('status', '=', Order::PENDING)
            ->update(['status' => Order::CANCELLED, 'updated_at' => $now->format('Y-m-d H:i:s')]);
    }

    /** A subscription whose page is gone, or has a higher plan than it, ends (and an automatic one stops being charged). */
    private function endIfOutdated(Subscription $sub, ?Page $page): bool
    {
        if ($page !== null && $this->plans->rank($page->plan) <= $this->plans->rank($sub->plan)) {
            return false;
        }
        if ($sub->mode === 'auto' && (string) $sub->provider_ref !== '') {
            try {
                $this->gateway()?->cancelSubscription((string) $sub->provider_ref);
            } catch (PaymentException) {
                logger()->error('[subscriptions] could not stop the provider charging subscription {id}: do it by hand', ['id' => $sub->id]);
            }
        }
        $sub->forceFill(['status' => Subscription::ENDED])->save();
        return true;
    }

    /**
     * Makes the renewal order of one manual subscription when it is due and none is waiting.
     *
     * @return ?Order the pending renewal (new or existing), or null when nothing is due
     */
    public function ensureRenewal(Subscription $sub, ?\DateTimeImmutable $now = null): ?Order
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if (!$sub->isActive() || $sub->page_id === null) {
            return null;
        }
        $page = Page::query()->withoutCache()->useWritePdo()->find($sub->page_id);
        if ($this->endIfOutdated($sub, $page instanceof Page ? $page : null)) {
            return null;
        }
        if ($sub->mode === 'auto') {
            return null; // the provider charges it; see syncAuto()
        }
        /** @var Page $page */
        if ($page->expires_at === null || $page->expires_at > $now->modify('+' . self::LEAD_DAYS . ' days')) {
            return null; // not due yet (or the page never expires)
        }
        $waiting = Order::query()->withoutCache()->useWritePdo()->where('subscription_id', $sub->id)->where('status', Order::PENDING)->first();
        if ($waiting instanceof Order) {
            return $waiting;
        }
        if (!$this->plans->canBuy($page, $sub->plan, $sub->period)) {
            return null;
        }
        $order = $this->billing->createOrder((int) $sub->user_id, $page, $sub->plan, $sub->period, $sub->provider);
        $order->forceFill(['method' => $sub->method, 'billing' => Order::SUBSCRIPTION, 'subscription_id' => $sub->id])->save();
        return $order;
    }

    /** Renewal orders for every manual subscription that is due (run by the scheduler, and for one owner when they open their dashboard). */
    public function createDueRenewals(?int $userId = null, ?\DateTimeImmutable $now = null): int
    {
        $query = Subscription::query()->withoutCache()->useWritePdo()->where('status', Subscription::ACTIVE)->where('mode', 'manual');
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }
        $made = 0;
        foreach ($query->get() as $sub) {
            $before = Order::query()->withoutCache()->useWritePdo()->where('subscription_id', $sub->id)->where('status', Order::PENDING)->exists();
            if (!$before && $this->ensureRenewal($sub, $now) instanceof Order) {
                $made++;
            }
        }
        return $made;
    }

    /**
     * Asks the provider about every automatic subscription: an invoice it has paid since last time extends the page (as a
     * paid renewal order), and a subscription it cancelled or gave up on ends here too.
     *
     * @return int how many periods were credited
     */
    public function syncAuto(?int $userId = null, ?\DateTimeImmutable $now = null): int
    {
        $gateway = $this->gateway();
        if ($gateway === null) {
            return 0;
        }
        $query = Subscription::query()->withoutCache()->useWritePdo()->where('status', Subscription::ACTIVE)->where('mode', 'auto');
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }
        $credited = 0;
        foreach ($query->get() as $sub) {
            $page = $sub->page_id !== null ? Page::query()->withoutCache()->useWritePdo()->find($sub->page_id) : null;
            if ($this->endIfOutdated($sub, $page instanceof Page ? $page : null)) {
                continue;
            }
            $state = $gateway->subscriptionState((string) $sub->provider_ref);
            if ($state === null) {
                continue;
            }
            if (in_array($state['status'], ['cancelled', 'incomplete_cancelled', 'unpaid'], true)) {
                $sub->forceFill(['status' => $state['status'] === 'cancelled' ? Subscription::CANCELLED : Subscription::ENDED, 'cancelled_at' => ($now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s')])->save();
                continue;
            }
            $invoice = $state['invoice_ref'];
            if ($invoice === null || !$state['invoice_paid'] || $invoice === $sub->last_invoice_ref) {
                continue;
            }
            if ($sub->last_invoice_ref !== null) {
                $credited += $this->creditPeriod($sub, $invoice, $state['amount'], $now) ? 1 : 0;
            }
            $sub->forceFill(['last_invoice_ref' => $invoice])->save(); // the first sight of an invoice is the payment that started it
        }
        return $credited;
    }

    /** A period the provider charged for: a renewal order, paid now, extends the page. */
    private function creditPeriod(Subscription $sub, string $invoice, ?int $amount, ?\DateTimeImmutable $now): bool
    {
        $page = Page::query()->withoutCache()->useWritePdo()->find((int) $sub->page_id);
        if (!$page instanceof Page) {
            return false;
        }
        try {
            $order = $this->billing->createOrder((int) $sub->user_id, $page, $sub->plan, $sub->period, $sub->provider);
        } catch (\DomainException) {
            logger()->error('[subscriptions] {provider} charged invoice {invoice} for a plan the page cannot renew: refund it', ['provider' => $sub->provider, 'invoice' => $invoice]);
            return false;
        }
        $order->forceFill(['method' => $sub->method, 'billing' => Order::SUBSCRIPTION, 'subscription_id' => $sub->id] + ($amount !== null ? ['amount' => $amount] : []))->save();
        return $this->billing->markPaid($order, $sub->provider, $invoice, $now);
    }

    private function gateway(): ?OnlineGateway
    {
        try {
            $gateway = app(PaymentGateway::class);
        } catch (\Throwable) {
            return null;
        }
        return $gateway instanceof OnlineGateway && $gateway->onlineConfigured() ? $gateway : null;
    }
}

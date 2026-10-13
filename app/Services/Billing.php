<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Page;
use Naluz\Database\Connection;

/**
 * Orders and plan activation.
 *
 * An order is activated exactly once: the switch from pending to paid is a single conditional UPDATE, so a payment
 * confirmed twice (a webhook and the return page, a double click, an admin re-running the grant script) changes the page
 * only the first time.
 */
final class Billing
{
    public function __construct(private readonly Plans $plans, private readonly Connection $db)
    {
    }

    /** @throws \DomainException when the plan cannot be bought for this page */
    public function createOrder(int $userId, Page $page, string $plan, string $provider): Order
    {
        $details = $this->plans->get($plan);
        if ($details === null || !$this->plans->canBuy($page, $plan)) {
            throw new \DomainException('That plan cannot be bought for this page.');
        }
        $order = new Order();
        $order->forceFill([
            'reference' => $this->uniqueReference(),
            'user_id' => $userId,
            'page_id' => $page->id,
            'plan' => $plan,
            'amount' => (int) $details['price'],
            'currency' => $this->plans->currency(),
            'status' => Order::PENDING,
            'provider' => $provider,
        ])->save();
        return $order;
    }

    /**
     * Marks the order paid and activates its plan on the page. Returns false (and changes nothing) when the order was
     * already paid or is no longer pending.
     */
    public function markPaid(Order $order, string $provider, ?string $providerRef = null, ?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return (bool) $this->db->transaction(function () use ($order, $provider, $providerRef, $now): bool {
            $changed = $this->db->table('orders')
                ->where('id', '=', $order->id)
                ->where('status', '=', Order::PENDING)
                ->update([
                    'status' => Order::PAID,
                    'provider' => $provider,
                    'provider_ref' => $providerRef,
                    'paid_at' => $now->format('Y-m-d H:i:s'),
                    'updated_at' => $now->format('Y-m-d H:i:s'),
                ]);
            if ($changed !== 1) {
                return false;
            }

            $page = $order->page_id !== null ? Page::query()->withoutCache()->useWritePdo()->find($order->page_id) : null;
            if ($page instanceof Page) {
                $this->activate($page, $order->plan, $now);
                $this->cancelOutdated($page, $order->id, $now);
            } else {
                logger()->warning('[billing] order {reference} was paid but its page no longer exists: refund it', ['reference' => $order->reference]);
            }
            $order->refresh();
            return true;
        });
    }

    /**
     * Puts the plan on the page. Time-limited plans add their days to whatever time is left (a renewal never loses
     * days); a plan that never expires clears the expiry. A lower plan than the page already has never downgrades it
     * (orders for one are cancelled when the higher plan starts, see cancelOutdated()); defensively it only adds its days
     * when the current plan is time-limited.
     */
    public function activate(Page $page, string $plan, \DateTimeImmutable $now): void
    {
        $details = $this->plans->get($plan) ?? throw new \DomainException("Unknown plan [{$plan}].");
        $current = $this->plans->get($page->plan);
        $currentIsPaid = $current !== null && $current['publish'];

        $keepCurrent = $currentIsPaid && $this->plans->rank($plan) < $this->plans->rank($page->plan);
        if ($keepCurrent) {
            logger()->warning('[billing] page {slug} already has the higher plan {current}; an order for {plan} only extends it', [
                'slug' => $page->slug,
                'current' => $page->plan,
                'plan' => $plan,
            ]);
            if ($current['days'] === null) {
                return;
            }
        }

        $days = $details['days'];
        if ($keepCurrent || $days !== null) {
            $left = $currentIsPaid && $page->expires_at !== null && $page->expires_at > $now ? $page->expires_at : $now;
            $expires = $left->modify('+' . (int) $days . ' days');
        } else {
            $expires = null;
        }

        $page->forceFill([
            'plan' => $keepCurrent ? $page->plan : $plan,
            'expires_at' => $expires,
            'published_at' => $page->published_at ?? $now,
        ])->save();
    }

    /**
     * Pending orders of the page that its new plan made pointless (a cheaper plan, or anything once it is Forever) are
     * cancelled, so paying one late can never be taken as buying it. Renewals of the same plan stay open.
     */
    private function cancelOutdated(Page $page, int $paidOrderId, \DateTimeImmutable $now): void
    {
        $pending = Order::query()->withoutCache()->useWritePdo()
            ->where('page_id', $page->id)->where('status', Order::PENDING)->where('id', '!=', $paidOrderId)->get()->all();
        foreach ($pending as $other) {
            if (!$this->plans->canBuy($page, $other->plan)) {
                $this->db->table('orders')->where('id', '=', $other->id)->where('status', '=', Order::PENDING)
                    ->update(['status' => Order::CANCELLED, 'updated_at' => $now->format('Y-m-d H:i:s')]);
            }
        }
    }

    private function uniqueReference(): string
    {
        do {
            $reference = Order::newReference();
        } while (Order::query()->withoutCache()->useWritePdo()->where('reference', $reference)->exists());
        return $reference;
    }
}

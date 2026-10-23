<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Page;
use App\Models\User;
use Naluz\Database\Connection;

/**
 * Orders, plan activation and photo packs.
 *
 * An order is activated exactly once: the switch from pending to paid is a single conditional UPDATE, so a payment
 * confirmed twice (a webhook and the return page, a double click, an admin re-running the grant script) changes the page
 * only the first time.
 */
final class Billing
{
    public function __construct(private readonly Plans $plans, private readonly Connection $db, private readonly PhotoAllowance $allowance)
    {
    }

    /** @throws \DomainException when the plan cannot be bought for this page in that period */
    public function createOrder(int $userId, Page $page, string $plan, string $period, string $provider): Order
    {
        $price = $this->plans->price($plan, $period);
        if ($price === null || !$this->plans->canBuy($page, $plan, $period)) {
            throw new \DomainException('That plan cannot be bought for this page.');
        }
        $order = new Order();
        $order->forceFill([
            'reference' => $this->uniqueReference(),
            'user_id' => $userId,
            'page_id' => $page->id,
            'plan' => $plan,
            'period' => $period,
            'amount' => $this->chargeFor($userId, $price),
            'currency' => $this->plans->currency(),
            'status' => Order::PENDING,
            'provider' => $provider,
        ])->save();
        return $order;
    }

    /**
     * An order for photo packs: `$packs` × the pack's photos for the account, at the pack price each. `$page` is the page it
     * was bought from (where to come back to); the photos belong to the account and stay when that page is deleted.
     *
     * @throws \DomainException when the number of packs is out of range
     */
    public function createPhotoOrder(int $userId, ?Page $page, int $packs, string $provider): Order
    {
        if ($packs < 1 || $packs > $this->allowance->maxPacks()) {
            throw new \DomainException(sprintf('Choose between 1 and %d packs of photos.', $this->allowance->maxPacks()));
        }
        $order = new Order();
        $order->forceFill([
            'reference' => $this->uniqueReference(),
            'user_id' => $userId,
            'page_id' => $page?->id,
            'plan' => Order::PHOTO_PACK,
            'period' => 'once',
            'photos' => $packs * $this->allowance->packPhotos(),
            'amount' => $this->chargeFor($userId, $packs * $this->allowance->packPrice()),
            'currency' => $this->plans->currency(),
            'status' => Order::PENDING,
            'provider' => $provider,
        ])->save();
        return $order;
    }

    /**
     * What the customer is charged: the price, except for the QA account (QA_EMAIL), which pays at most the testing amount (₱1).
     * The plan, the period and the photos it buys are unchanged; only the money is.
     */
    private function chargeFor(int $userId, int $price): int
    {
        $email = User::normaliseEmail((string) config('saas.qa.email', ''));
        $cap = (int) config('saas.qa.amount', 100);
        if ($email === '' || $cap < 1 || $price <= $cap) {
            return $price;
        }
        $user = User::query()->withoutCache()->where('id', $userId)->first();
        return $user instanceof User && $user->email === $email ? $cap : $price;
    }

    /**
     * Marks the order paid and activates its plan on the page (a photo pack needs nothing more: paid packs count
     * towards the account's allowance from then on). Returns false (and changes nothing) when the order was
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
                    // raw SQL does not go through the model, so the sealing is done here (App\Services\PaymentVault)
                    'provider_ref' => PaymentVault::seal($providerRef),
                    'provider_ref_idx' => PaymentVault::index($providerRef),
                    'paid_at' => $now->format('Y-m-d H:i:s'),
                    'updated_at' => $now->format('Y-m-d H:i:s'),
                ]);
            if ($changed !== 1) {
                return false;
            }
            if ($order->isPhotoPack()) {
                $order->refresh();
                return true;
            }

            $page = $order->page_id !== null ? Page::query()->withoutCache()->useWritePdo()->find($order->page_id) : null;
            if ($page instanceof Page) {
                $this->activate($page, $order->plan, (string) $order->period, $now);
                $this->cancelOutdated($page, $order->id, $now);
            } else {
                logger()->warning('[billing] order {reference} was paid but its page no longer exists: refund it', ['reference' => $order->reference]);
            }
            $order->refresh();
            (new Subscriptions($this->db, $this->plans, $this))->recordPaid($order); // a subscription order starts or renews the subscription
            return true;
        });
    }

    /** A pending order the provider says will not be paid (its checkout expired). Returns false when it was not pending. */
    public function markCancelled(Order $order, ?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return $this->db->table('orders')->where('id', '=', $order->id)->where('status', '=', Order::PENDING)
            ->update(['status' => Order::CANCELLED, 'updated_at' => $now->format('Y-m-d H:i:s')]) === 1;
    }

    /**
     * Puts the plan on the page for the period paid. A month or a year is added to whatever time is left (a renewal
     * never loses days; Jan 31 + 1 month is the end of February); a plan bought once clears the expiry. A lower plan
     * than the page already has never downgrades it (orders for one are cancelled when the higher plan starts, see
     * cancelOutdated()); defensively it only adds its months when the current plan is time-limited.
     */
    public function activate(Page $page, string $plan, string $period, \DateTimeImmutable $now): void
    {
        if ($this->plans->get($plan) === null) {
            throw new \DomainException("Unknown plan [{$plan}].");
        }
        $months = $this->plans->months($period);
        $current = $this->plans->get($page->plan);
        $currentIsPaid = $current !== null && $current['publish'];

        $keepCurrent = $currentIsPaid && $this->plans->rank($plan) < $this->plans->rank($page->plan);
        if ($keepCurrent) {
            logger()->warning('[billing] page {slug} already has the higher plan {current}; an order for {plan} only extends it', [
                'slug' => $page->slug,
                'current' => $page->plan,
                'plan' => $plan,
            ]);
            if ($this->plans->isPermanent($page->plan) && $page->expires_at === null) {
                return;
            }
        }

        if ($months !== null) {
            $left = $currentIsPaid && $page->expires_at !== null && $page->expires_at > $now ? $page->expires_at : $now;
            $expires = Plans::addMonths($left, $months);
        } elseif ($keepCurrent) {
            return;
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
            ->where('page_id', $page->id)->where('status', Order::PENDING)->where('id', '!=', $paidOrderId)
            ->where('plan', '!=', Order::PHOTO_PACK)->get()->all();
        foreach ($pending as $other) {
            if (!$this->plans->canBuy($page, $other->plan, (string) $other->period)) {
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

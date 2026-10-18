<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Page;
use App\Models\Photo;

/**
 * How many photos an account may keep, and how many it has.
 *
 * The allowance belongs to the account, not to one page: every photo on every page counts, and deleting a photo (or
 * its page) frees its place. It is what the plans of the account's live pages include, added up (or the free
 * preview's, while no page is live), plus the photo packs the account bought, which never expire. A plan that ends
 * stops counting: nothing is deleted or hidden then, but no photo can be added until the account is under its
 * allowance again.
 */
final class PhotoAllowance
{
    public function __construct(
        private readonly Plans $plans,
        private readonly int $packPhotos,
        private readonly int $packPrice,
        private readonly int $maxPacks,
    ) {
        if ($packPhotos < 1 || $packPrice < 1 || $maxPacks < 1) {
            throw new \InvalidArgumentException('A photo pack needs at least one photo, a price and at least one pack per order.');
        }
    }

    public function packPhotos(): int
    {
        return $this->packPhotos;
    }

    public function packPrice(): int
    {
        return $this->packPrice;
    }

    public function maxPacks(): int
    {
        return $this->maxPacks;
    }

    /**
     * What an order of 1 to maxPacks() packs adds and costs: packs => [photos, price in centavos].
     *
     * @return array<int,array{photos:int,price:int}>
     */
    public function packOptions(): array
    {
        $out = [];
        for ($n = 1; $n <= $this->maxPacks; $n++) {
            $out[$n] = ['photos' => $n * $this->packPhotos, 'price' => $n * $this->packPrice];
        }
        return $out;
    }

    /**
     * The account's photos right now. Read from the primary database, never a cache, so a check made just before an
     * upload sees the latest photos, plans and payments.
     *
     * @return array{used:int,total:int,remaining:int,over:int,included:int,bought:int,packs_needed:int,sources:list<array{recipient:string,plan:string,photos:int}>}
     *         `included`: from the plans (`sources`: one entry per live page; empty while the free preview's applies),
     *         `bought`: from packs, `over`: photos beyond the allowance, `packs_needed`: packs to buy before one more
     *         photo fits (0 while there is room)
     */
    public function of(int $userId, ?\DateTimeImmutable $now = null): array
    {
        $pages = Page::query()->withoutCache()->useWritePdo()->where('user_id', $userId)->orderBy('id')->get()->all();

        $sources = [];
        $included = 0;
        foreach ($pages as $page) {
            if ($page->isLive($now)) {
                $plan = $this->plans->get($page->plan);
                $photos = (int) ($plan['photos'] ?? 0);
                $included += $photos;
                $sources[] = ['recipient' => $page->recipient, 'plan' => (string) ($plan['name'] ?? $page->plan), 'photos' => $photos];
            }
        }
        if ($sources === []) {
            $included = $this->freePhotos();
        }

        $bought = (int) Order::query()->withoutCache()->useWritePdo()
            ->where('user_id', $userId)->where('plan', Order::PHOTO_PACK)->where('status', Order::PAID)->sum('photos');

        $ids = array_map(static fn (Page $p): int => $p->id, $pages);
        $used = $ids === [] ? 0 : Photo::query()->withoutCache()->useWritePdo()->whereIn('page_id', $ids)->count();

        $total = $included + $bought;
        $remaining = max(0, $total - $used);
        $over = max(0, $used - $total);
        return [
            'used' => $used,
            'total' => $total,
            'remaining' => $remaining,
            'over' => $over,
            'included' => $included,
            'bought' => $bought,
            'packs_needed' => $remaining > 0 ? 0 : (int) ceil(($over + 1) / $this->packPhotos),
            'sources' => $sources,
        ];
    }

    /** Why an upload was refused, and what to do about it. @param array{used:int,total:int,over:int} $usage */
    public function fullMessage(array $usage): string
    {
        $pack = sprintf('%d more for %s', $this->packPhotos, Plans::money($this->packPrice, $this->plans->currency()));
        if ($usage['over'] > 0) {
            return sprintf(
                'Your account holds %d photos and now includes %d, since a plan ended. Your photos stay as they are; to add new ones, delete %d or buy %s.',
                $usage['used'],
                $usage['total'],
                $usage['over'] + 1,
                $pack,
            );
        }
        return sprintf('You have used all %d photos of your account. Buy %s to keep adding, or delete one to make room.', $usage['total'], $pack);
    }

    /** The allowance of an account with no live page: the free preview's. */
    public function freePhotos(): int
    {
        foreach ($this->plans->all() as $plan) {
            if (!$plan['publish']) {
                return (int) $plan['photos'];
            }
        }
        return 0;
    }
}

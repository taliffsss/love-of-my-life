<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Page;
use App\Models\Photo;
use App\Models\User;
use Naluz\Database\Connection;

/**
 * Deletes an account the soft way: the account, its pages and their photos get one `deleted_at` (so they vanish from the product and the pages' links
 * stop working), its subscriptions end and its unpaid orders are cancelled. Nothing is removed: the rows and the files stay, and bin/trash.php restores
 * the account together with exactly the pages and photos that went with it. Paid orders are financial records, so an account that has any is
 * refused (deactivate it instead).
 */
final class AccountRemoval
{
    public function __construct(private readonly Connection $db, private readonly PhotoStorage $storage)
    {
    }

    /** Why this account cannot be deleted, or null when it can. */
    public function blocker(User $user): ?string
    {
        if ($user->is_admin) {
            return 'An administrator cannot be deleted. Remove the administrator role first.';
        }
        if ($this->db->table('orders')->where('user_id', '=', $user->id)->where('status', '=', Order::PAID)->count() > 0) {
            return 'This account has paid orders, which must stay on record. Deactivate it instead.';
        }
        return null;
    }

    /** @return array{pages:int,photos:int} what was hidden @throws \DomainException when blocker() says so */
    public function delete(User $user): array
    {
        if (($why = $this->blocker($user)) !== null) {
            throw new \DomainException($why);
        }

        $pageIds = array_map(static fn (Page $p): int => $p->id, Page::query()->withoutCache()->useWritePdo()->where('user_id', $user->id)->get()->all());
        $photoCount = $pageIds === [] ? 0 : Photo::query()->withoutCache()->useWritePdo()->whereIn('page_id', $pageIds)->count();
        $stamp = gmdate('Y-m-d H:i:s');

        $this->db->transaction(function () use ($user, $pageIds, $stamp): void {
            if ($pageIds !== []) {
                $this->db->table('photos')->whereIn('page_id', $pageIds)->whereNull('deleted_at')->update(['deleted_at' => $stamp]);
                $this->db->table('pages')->whereIn('id', $pageIds)->update(['deleted_at' => $stamp]);
            }
            $this->db->table('orders')->where('user_id', '=', $user->id)->where('status', '!=', Order::PAID)->update(['status' => Order::CANCELLED]);
            $this->db->table('subscriptions')->where('user_id', '=', $user->id)->update(['status' => 'ended']);
            $this->db->table('users')->where('id', '=', $user->id)->update(['deleted_at' => $stamp]);
        });

        return ['pages' => count($pageIds), 'photos' => $photoCount];
    }

    /**
     * Restores a deleted account with exactly the pages and photos that were deleted together with it (the same `deleted_at`).
     *
     * @return array{pages:int,photos:int}
     */
    public function restore(User $user): array
    {
        $stamp = $user->getRawAttributes()['deleted_at'] ?? null;
        if ($stamp === null) {
            throw new \DomainException('This account is not deleted.');
        }
        $pages = $this->db->table('pages')->where('user_id', '=', $user->id)->where('deleted_at', '=', $stamp)->pluck('id')->all();
        $photos = 0;
        $this->db->transaction(function () use ($user, $stamp, $pages, &$photos): void {
            $this->db->table('users')->where('id', '=', $user->id)->update(['deleted_at' => null]);
            if ($pages !== []) {
                $photos = $this->db->table('photos')->whereIn('page_id', $pages)->where('deleted_at', '=', $stamp)->update(['deleted_at' => null]);
                $this->db->table('pages')->whereIn('id', $pages)->update(['deleted_at' => null]);
            }
        });
        return ['pages' => count($pages), 'photos' => (int) $photos];
    }

    /**
     * Permanent erasure of an account that is ALREADY deleted (a privacy request): its pages and photos, files included, its social sign-ins, its subscriptions and
     * unpaid orders. Paid orders are financial records and stay, so an account with any is refused. This is the only way anything is removed for good.
     *
     * @return array{pages:int,photos:int}
     */
    public function erase(User $user): array
    {
        if (($user->getRawAttributes()['deleted_at'] ?? null) === null) {
            throw new \DomainException('Only a deleted account can be erased: delete it first.');
        }
        if (($why = $this->blocker($user)) !== null) {
            throw new \DomainException($why);
        }
        $pageIds = array_map(static fn (Page $p): int => $p->id, Page::query()->withTrashed()->withoutCache()->useWritePdo()->where('user_id', $user->id)->get()->all());
        $photos = $pageIds === [] ? [] : Photo::query()->withTrashed()->withoutCache()->useWritePdo()->whereIn('page_id', $pageIds)->get()->all();
        $this->db->transaction(function () use ($user, $pageIds, $photos): void {
            if ($pageIds !== []) {
                $this->db->table('photos')->whereIn('page_id', $pageIds)->delete();
                $this->storage->trash(...$photos);
                $pageOrderIds = $this->db->table('orders')->whereIn('page_id', $pageIds)->where('status', '!=', Order::PAID)->pluck('id')->all();
                if ($pageOrderIds !== []) {
                    $this->db->table('payment_attempts')->whereIn('order_id', $pageOrderIds)->delete();
                }
                $this->db->table('orders')->whereIn('page_id', $pageIds)->where('status', '!=', Order::PAID)->delete();
                $this->db->table('pages')->whereIn('id', $pageIds)->delete();
            }
            $orderIds = $this->db->table('orders')->where('user_id', '=', $user->id)->pluck('id')->all();
            if ($orderIds !== []) {
                $this->db->table('payment_attempts')->whereIn('order_id', $orderIds)->delete();
            }
            $this->db->table('orders')->where('user_id', '=', $user->id)->delete(); // none paid: checked above
            $this->db->table('subscriptions')->where('user_id', '=', $user->id)->delete();
            $this->db->table('social_accounts')->where('user_id', '=', $user->id)->delete();
            $this->db->table('users')->where('id', '=', $user->id)->delete();
        });
        $this->storage->purge(...$photos);

        return ['pages' => count($pageIds), 'photos' => count($photos)];
    }
}

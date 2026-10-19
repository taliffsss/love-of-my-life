<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Page;
use App\Models\Photo;
use App\Models\User;
use Naluz\Database\Connection;

/**
 * Deletes an account for good: its pages (with their photos, whose files go to the storage trash and are then deleted, as
 * when an owner deletes a page), its social sign-ins and its unpaid orders. Paid orders are financial records and stay, so an
 * account that has any is refused (deactivate it instead).
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

    /** @return array{pages:int,photos:int} what was removed @throws \DomainException when blocker() says so */
    public function delete(User $user): array
    {
        if (($why = $this->blocker($user)) !== null) {
            throw new \DomainException($why);
        }

        $pageIds = array_map(static fn (Page $p): int => $p->id, Page::query()->withoutCache()->useWritePdo()->where('user_id', $user->id)->get()->all());
        $photos = $pageIds === [] ? [] : Photo::query()->withoutCache()->useWritePdo()->whereIn('page_id', $pageIds)->get()->all();

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
        // the files go once the rows are gone: one that cannot be deleted now waits in the trash (bin/empty-trash.php)
        $this->storage->purge(...$photos);

        return ['pages' => count($pageIds), 'photos' => count($photos)];
    }
}

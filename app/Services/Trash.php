<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Page;
use App\Models\Photo;
use App\Models\User;
use Naluz\Database\Connection;

/**
 * What can be done with deleted pages and photos (deleting itself is soft: `deleted_at`, see the controllers and AccountRemoval): bring them back, or, only
 * for a privacy request, erase what is already deleted for good. Used by bin/trash.php.
 */
final class Trash
{
    public function __construct(private readonly Connection $db, private readonly PhotoStorage $storage)
    {
    }

    /** Brings a deleted page back with the photos that were deleted together with it (the same `deleted_at`). Its subscription stays ended. @return int photos restored */
    public function restorePage(Page $page): int
    {
        $stamp = $page->getRawAttributes()['deleted_at'] ?? null;
        if ($stamp === null) {
            throw new \DomainException('This page is not deleted.');
        }
        if (!User::query()->find($page->user_id) instanceof User) {
            throw new \DomainException("Its owner (user #{$page->user_id}) is deleted: restore the user first.");
        }
        $photos = 0;
        $this->db->transaction(function () use ($page, $stamp, &$photos): void {
            $this->db->table('pages')->where('id', '=', $page->id)->update(['deleted_at' => null]);
            $photos = (int) $this->db->table('photos')->where('page_id', '=', $page->id)->where('deleted_at', '=', $stamp)->update(['deleted_at' => null]);
        });
        return $photos;
    }

    public function restorePhoto(Photo $photo): void
    {
        if (($photo->getRawAttributes()['deleted_at'] ?? null) === null) {
            throw new \DomainException('This photo is not deleted.');
        }
        if (!Page::query()->find($photo->page_id) instanceof Page) {
            throw new \DomainException("Its page (#{$photo->page_id}) is deleted: restore the page first.");
        }
        $photo->restore();
    }

    /** Erases an already deleted page and all its photos, files included. Paid orders stay on record. @return int photos erased */
    public function erasePage(Page $page): int
    {
        if (($page->getRawAttributes()['deleted_at'] ?? null) === null) {
            throw new \DomainException('Only a deleted page can be erased: delete it first.');
        }
        $photos = Photo::query()->withTrashed()->withoutCache()->useWritePdo()->where('page_id', $page->id)->get()->all();
        $this->db->transaction(function () use ($page, $photos): void {
            $this->db->table('photos')->where('page_id', '=', $page->id)->delete();
            $this->storage->trash(...$photos);
            $this->db->table('orders')->where('page_id', '=', $page->id)->update(['page_id' => null]);
            $this->db->table('subscriptions')->where('page_id', '=', $page->id)->delete();
            $this->db->table('pages')->where('id', '=', $page->id)->delete();
        });
        $this->storage->purge(...$photos);
        return count($photos);
    }

    public function erasePhoto(Photo $photo): void
    {
        if (($photo->getRawAttributes()['deleted_at'] ?? null) === null) {
            throw new \DomainException('Only a deleted photo can be erased: delete it first.');
        }
        $this->db->transaction(function () use ($photo): void {
            $photo->forceDelete();
            $this->storage->trash($photo);
        });
        $this->storage->purge($photo);
    }
}

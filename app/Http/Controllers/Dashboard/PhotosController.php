<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Screen;
use App\Models\Photo;
use App\Services\PhotoAllowance;
use App\Services\PhotoStorage;
use Naluz\Auth\Auth;
use Naluz\Database\Connection;
use Naluz\Http\HttpException;
use Naluz\Http\Request;
use Naluz\Http\Response;
use Naluz\Storage\StorageException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Photos of a page, managed from the dashboard (the browser resizes each photo before it is sent). Every photo counts
 * towards the account's allowance (App\Services\PhotoAllowance), checked here, on the server, before and after each
 * upload.
 */
final class PhotosController
{
    use OwnsPages;

    public function store(string $slug, ServerRequestInterface $request, Auth $auth, PhotoStorage $storage, PhotoAllowance $allowance, Connection $db): ResponseInterface
    {
        $page = $this->ownedPage($auth, $slug);
        $userId = (int) $auth->id();
        $usage = $allowance->of($userId);
        if (!$usage['subscribed']) {
            return Response::json(['message' => $allowance->subscribeMessage(), 'code' => 'subscription_required', 'usage' => self::usage($usage)], 422);
        }
        if ($usage['remaining'] <= 0) {
            return $this->full($allowance, $usage);
        }

        $file = $request->getUploadedFiles()['photo'] ?? null;
        if (!$file instanceof UploadedFileInterface) {
            // a body over PHP's post_max_size arrives without its files
            $length = (int) $request->getHeaderLine('Content-Length');
            throw new HttpException(422, $length > $storage->maxBytes() ? self::tooLarge($length, $storage->maxBytes()) : 'Choose a photo to upload.');
        }
        $size = (int) $file->getSize();
        if (in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $size > $storage->maxBytes()) {
            throw new HttpException(422, self::tooLarge($size, $storage->maxBytes()));
        }

        try {
            $stored = $storage->store($file, $page);
        } catch (StorageException $e) {
            logger()->warning('[photos] upload failed: {error}', ['error' => $e->getMessage() . ($e->getPrevious() ? ' / ' . $e->getPrevious()->getMessage() : '')]);
            throw new HttpException(422, $e->getMessage());
        }

        $photo = new Photo();
        try {
            $photo->forceFill([
                'page_id' => $page->id,
                'disk' => $stored['disk'],
                'path' => $stored['path'],
                'url' => '', // the key (disk + path) is what is kept; PhotoStorage::photoUrl() makes the address
                'position' => (int) Photo::query()->withoutCache()->useWritePdo()->where('page_id', $page->id)->max('position') + 1,
            ])->save();
        } catch (\Throwable $e) {
            $storage->discard($stored['disk'], $stored['path']);
            throw $e;
        }

        // Two uploads racing past the first check: one that lands beyond the allowance is undone, so it always holds.
        $usage = $allowance->of($userId);
        if ($usage['over'] > 0) {
            $this->discardUpload($photo, $storage, $db);
            return $this->full($allowance, $allowance->of($userId));
        }

        return Response::json([
            'id' => $photo->id,
            'url' => $stored['url'],
            'count' => $this->pageCount($page->id),
            'usage' => self::usage($usage),
        ], 201);
    }

    public function destroy(string $slug, int $photo, ServerRequestInterface $request, Auth $auth, PhotoStorage $storage, PhotoAllowance $allowance, Connection $db): ResponseInterface
    {
        $page = $this->ownedPage($auth, $slug);
        $model = Photo::query()->where('id', $photo)->where('page_id', $page->id)->first();
        if (!$model instanceof Photo) {
            throw new HttpException(404, 'Photo not found.');
        }
        $this->remove($model);

        if (Request::expectsJson($request)) {
            return Response::json([
                'deleted' => $model->id,
                'count' => $this->pageCount($page->id),
                'usage' => self::usage($allowance->of((int) $auth->id())),
            ]);
        }
        return Screen::back($this->editUrl($page, 'photos'), 'Photo deleted.');
    }

    /** Body: {"order": [photo ids in the new order]} — must list every photo of the page exactly once. */
    public function reorder(string $slug, ServerRequestInterface $request, Auth $auth, Connection $db): ResponseInterface
    {
        $page = $this->ownedPage($auth, $slug);
        $order = Request::body($request)['order'] ?? null;
        if (!is_array($order) || $order === []) {
            throw new HttpException(422, 'Send the photo ids in their new order.');
        }
        $ids = array_map(static fn ($id): int => is_numeric($id) ? (int) $id : 0, array_values($order));

        $current = array_map('intval', Photo::query()->where('page_id', $page->id)->pluck('id')->all());
        $sortedGiven = $ids;
        sort($sortedGiven);
        sort($current);
        if ($sortedGiven !== $current) {
            throw new HttpException(422, 'The new order must list every photo of this page once.');
        }

        $db->transaction(function () use ($db, $ids, $page): void {
            foreach ($ids as $position => $id) {
                $db->table('photos')->where('id', '=', $id)->where('page_id', '=', $page->id)->update(['position' => $position + 1]);
            }
        });
        return Response::json(['order' => $ids]);
    }

    /** Deleting a photo is soft: it is hidden and stops counting, its row and its file stay (bin/trash.php restores or erases it). */
    private function remove(Photo $photo): void
    {
        $photo->delete();
    }

    /** An upload that lost a race for the last place: it was never shown, so it is undone for good, file included. */
    private function discardUpload(Photo $photo, PhotoStorage $storage, Connection $db): void
    {
        $db->transaction(function () use ($photo, $storage): void {
            $photo->forceDelete();
            $storage->trash($photo);
        });
        $storage->purge($photo);
    }

    private function pageCount(int $pageId): int
    {
        return Photo::query()->withoutCache()->useWritePdo()->where('page_id', $pageId)->count();
    }

    /** 422 with what the dashboard needs to offer more photos. @param array<string,mixed> $usage */
    private function full(PhotoAllowance $allowance, array $usage): ResponseInterface
    {
        return Response::json(['message' => $allowance->fullMessage($usage), 'code' => 'photo_limit', 'usage' => self::usage($usage)], 422);
    }

    /** @param array<string,mixed> $usage @return array{used:int,total:int,remaining:int,over:int,packs_needed:int,subscribed:bool} */
    private static function usage(array $usage): array
    {
        return [
            'used' => (int) $usage['used'],
            'total' => (int) $usage['total'],
            'remaining' => (int) $usage['remaining'],
            'over' => (int) $usage['over'],
            'packs_needed' => (int) $usage['packs_needed'],
            'subscribed' => (bool) $usage['subscribed'],
        ];
    }

    /** "This photo is 14.2 MB. Photos can be up to 10 MB each." */
    public static function tooLarge(int $bytes, int $maxBytes): string
    {
        $max = 'Photos can be up to ' . PhotoStorage::megabytes($maxBytes) . ' each.';
        return $bytes > $maxBytes ? 'This photo is ' . PhotoStorage::megabytes($bytes) . '. ' . $max : 'This photo is too large. ' . $max;
    }
}

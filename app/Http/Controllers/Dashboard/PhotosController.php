<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Screen;
use App\Models\Photo;
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

/** Photos of a page, managed from the dashboard (the browser resizes each photo before it is sent). */
final class PhotosController
{
    use OwnsPages;

    public function store(string $slug, ServerRequestInterface $request, Auth $auth, PhotoStorage $storage): ResponseInterface
    {
        $page = $this->ownedPage($auth, $slug);
        $limit = $page->photoLimit();
        $count = fn (): int => Photo::query()->withoutCache()->useWritePdo()->where('page_id', $page->id)->count();
        if ($count() >= $limit) {
            throw new HttpException(422, "This page already has {$limit} photos, the most its plan allows. Delete one or upgrade the plan.");
        }

        $file = $request->getUploadedFiles()['photo'] ?? null;
        if (!$file instanceof UploadedFileInterface) {
            throw new HttpException(422, 'Choose a photo to upload.');
        }

        try {
            $stored = $storage->store($file, $page);
        } catch (StorageException $e) {
            logger()->warning('[photos] upload failed: {error}', ['error' => $e->getMessage() . ($e->getPrevious() ? ' / ' . $e->getPrevious()->getMessage() : '')]);
            throw new HttpException(422, $e->getMessage());
        }

        $photo = new Photo();
        $photo->forceFill([
            'page_id' => $page->id,
            'disk' => $stored['disk'],
            'path' => $stored['path'],
            'url' => $stored['url'],
            'position' => (int) Photo::query()->withoutCache()->useWritePdo()->where('page_id', $page->id)->max('position') + 1,
        ])->save();

        // Two uploads racing past the first check: the later one is undone, so the limit always holds.
        $total = $count();
        if ($total > $limit) {
            $photo->delete();
            $storage->delete($photo);
            throw new HttpException(422, "This page already has {$limit} photos, the most its plan allows.");
        }

        return Response::json(['id' => $photo->id, 'url' => $photo->url, 'count' => $total, 'limit' => $limit], 201);
    }

    public function destroy(string $slug, int $photo, ServerRequestInterface $request, Auth $auth, PhotoStorage $storage): ResponseInterface
    {
        $page = $this->ownedPage($auth, $slug);
        $model = Photo::query()->where('id', $photo)->where('page_id', $page->id)->first();
        if (!$model instanceof Photo) {
            throw new HttpException(404, 'Photo not found.');
        }
        $model->delete();
        $storage->delete($model);

        if (Request::expectsJson($request)) {
            return Response::json(['deleted' => $model->id, 'count' => Photo::query()->where('page_id', $page->id)->count()]);
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
}

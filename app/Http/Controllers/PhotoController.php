<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CarouselStore;
use App\Services\UploadGate;
use Naluz\Http\HttpException;
use Naluz\Http\Response;
use Naluz\Storage\StorageException;
use Naluz\Storage\Uploads;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

final class PhotoController
{
    public function store(ServerRequestInterface $request, CarouselStore $store, UploadGate $gate): ResponseInterface
    {
        $gate->assertAllowed($request);

        $file = $request->getUploadedFiles()['photo'] ?? null;
        if (!$file instanceof UploadedFileInterface) {
            throw new HttpException(422, 'Choose a photo to upload.');
        }

        try {
            $disk = storage('spaces');
        } catch (\InvalidArgumentException) {
            throw new HttpException(503, 'Photo storage is not configured. Check the storage settings in .env.');
        }

        $folder = trim((string) config('filesystems.disks.spaces.folder'), '/');
        try {
            $key = Uploads::store($file, $disk, $folder, Uploads::IMAGES, (int) config('site.max_upload_bytes'));
        } catch (StorageException $e) {
            error_log('[photos] storage upload failed: ' . $e->getMessage() . ($e->getPrevious() ? ' / ' . $e->getPrevious()->getMessage() : ''));
            return Response::json(['message' => $e->getMessage() . ' Check the storage settings in .env.'], 502);
        }

        $url = $disk->url($key);
        try {
            $store->add($key, $url);
        } catch (\RuntimeException $e) {
            error_log('[photos] ' . $e->getMessage());
            return Response::json(['message' => 'The photo was uploaded but storage/app is not writable, so it was not added to the carousel.'], 500);
        }

        return Response::json(['key' => $key, 'url' => $url], 201);
    }
}

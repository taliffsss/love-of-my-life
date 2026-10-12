<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CarouselStore;
use Naluz\Http\HttpException;
use Naluz\Http\Request;
use Naluz\Http\Response;
use Naluz\Storage\StorageException;
use Naluz\Storage\Uploads;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

final class PhotoController
{
    public function store(ServerRequestInterface $request, CarouselStore $store): ResponseInterface
    {
        $expected = (string) config('site.upload_key');
        if ($expected === '') {
            throw new HttpException(503, 'Uploads are not enabled. Set UPLOAD_KEY in .env.');
        }
        $given = (string) (Request::body($request)['key'] ?? '');
        if (!hash_equals($this->normalise($expected), $this->normalise($given))) {
            throw new HttpException(403, 'Wrong upload key. ' . config('site.upload_hint'));
        }

        $file = $request->getUploadedFiles()['photo'] ?? null;
        if (!$file instanceof UploadedFileInterface) {
            throw new HttpException(422, 'Choose a photo to upload.');
        }

        try {
            $disk = storage('spaces');
        } catch (\InvalidArgumentException) {
            throw new HttpException(503, 'DigitalOcean Spaces is not configured. Set the SPACES_* values in .env.');
        }

        $folder = trim((string) config('filesystems.disks.spaces.folder'), '/');
        try {
            $key = Uploads::store($file, $disk, $folder, Uploads::IMAGES, (int) config('site.max_upload_bytes'));
        } catch (StorageException $e) {
            error_log('[photos] Spaces upload failed: ' . $e->getMessage() . ($e->getPrevious() ? ' / ' . $e->getPrevious()->getMessage() : ''));
            return Response::json(['message' => $e->getMessage() . ' Check the SPACES_* values in .env.'], 502);
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

    /** The key is a name, so ignore case and extra spaces when comparing. */
    private function normalise(string $key): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($key)));
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CarouselStore;
use Naluz\Http\HttpException;
use Naluz\Http\Request;
use Naluz\Http\Response;
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
        if (!hash_equals($expected, $given)) {
            throw new HttpException(403, 'Wrong upload key.');
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
        $key = Uploads::store($file, $disk, $folder, Uploads::IMAGES, (int) config('site.max_upload_bytes'));
        $url = $disk->url($key);
        $store->add($key, $url);

        return Response::json(['key' => $key, 'url' => $url], 201);
    }
}

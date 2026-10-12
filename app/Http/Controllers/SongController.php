<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SongStore;
use App\Services\UploadGate;
use App\Services\YouTube;
use Naluz\Http\HttpException;
use Naluz\Http\Request;
use Naluz\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SongController
{
    public function update(ServerRequestInterface $request, UploadGate $gate, YouTube $youtube, SongStore $songs): ResponseInterface
    {
        $gate->assertAllowed($request);

        $id = YouTube::parseId((string) (Request::body($request)['url'] ?? ''));
        if ($id === null) {
            throw new HttpException(422, 'Paste a YouTube link, for example https://www.youtube.com/watch?v=fu9yk7gCTbc');
        }

        try {
            $info = $youtube->lookup($id);
        } catch (\InvalidArgumentException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        try {
            $songs->set($id, $info['title'], $info['artist']);
        } catch (\RuntimeException $e) {
            error_log('[song] ' . $e->getMessage());
            return Response::json(['message' => 'Could not save the song: storage/app is not writable.'], 500);
        }

        return Response::json(['id' => $id] + $info);
    }
}

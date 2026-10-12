<?php

declare(strict_types=1);

namespace App\Services;

use Naluz\Http\HttpException;
use Naluz\Http\Request;
use Psr\Http\Message\ServerRequestInterface;

/** Checks the secret "upload key" that protects every change made from the page (photos and song). */
final class UploadGate
{
    /** @throws HttpException 503 while no key is configured, 403 for a wrong key */
    public function assertAllowed(ServerRequestInterface $request): void
    {
        $expected = (string) config('site.upload_key');
        if ($expected === '') {
            throw new HttpException(503, 'Uploads are not enabled. Set UPLOAD_KEY in .env.');
        }
        $given = (string) (Request::body($request)['key'] ?? '');
        if (!hash_equals($this->normalise($expected), $this->normalise($given))) {
            throw new HttpException(403, 'Wrong upload key. ' . config('site.upload_hint'));
        }
    }

    /**
     * The key is a name, so ignore case and extra spaces when comparing. Quotes around it are ignored too: Docker's
     * env_file keeps them literally ("jinelin basela" would otherwise never match what is typed).
     */
    private function normalise(string $key): string
    {
        $key = trim($key, " \t\r\n\"'");
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', $key));
    }
}

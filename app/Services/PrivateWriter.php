<?php

declare(strict_types=1);

namespace App\Services;

/** A disk that can store a file that is NOT publicly readable (used for archived messages). */
interface PrivateWriter
{
    public function putPrivate(string $path, string $contents): void;
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\CarouselStore;

final class PhotoController
{
    public function index(CarouselStore $store): array
    {
        return ['photos' => $store->all()];
    }
}

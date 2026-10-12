<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\CarouselStore;

final class PhotoController
{
    public function index(CarouselStore $store): array
    {
        // newest upload first: the order the carousel shows them in
        return ['photos' => array_reverse($store->all())];
    }
}

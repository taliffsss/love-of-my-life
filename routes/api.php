<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\StatusController;
use Naluz\Routing\Router;

/** @var Router $router  — mounted under /api, stateless, rate limited */
$router->get('/ping', [StatusController::class, 'ping'])->name('ping');

// Every uploaded photo URL, as stored in storage/app/carousel.json.
$router->get('/photos', [PhotoController::class, 'index'])->name('photos.index');

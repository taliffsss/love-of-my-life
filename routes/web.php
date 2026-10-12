<?php

declare(strict_types=1);

use App\Http\Controllers\HomeController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\SeoController;
use Naluz\Routing\Router;

/** @var Router $router */
$router->get('/', [HomeController::class, 'index'])->name('home');
$router->get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
$router->get('/robots.txt', [SeoController::class, 'robots'])->name('robots');

// Upload a photo to DigitalOcean Spaces and add it to the carousel (needs the upload key).
$router->post('/photos', [PhotoController::class, 'store'])->middleware('throttle:20,1')->name('photos.store');

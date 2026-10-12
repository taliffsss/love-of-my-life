<?php

declare(strict_types=1);

use App\Http\Controllers\HomeController;
use App\Http\Controllers\LetterController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SongController;
use Naluz\Routing\Router;

/** @var Router $router */
$router->get('/', [HomeController::class, 'index'])->name('home');
$router->get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
$router->get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
$router->get('/manifest.webmanifest', [SeoController::class, 'manifest'])->name('manifest');

// Upload a photo to DigitalOcean Spaces and add it to the carousel (needs the upload key).
$router->post('/upload', [PhotoController::class, 'store'])->middleware(['throttle:20,1', 'noindex'])->name('upload');

// Replace the song with another YouTube link (needs the upload key). Not /music: that is a real folder.
$router->post('/song', [SongController::class, 'update'])->middleware(['throttle:20,1', 'noindex'])->name('song');

// Replace the message (the letter). The old message is archived as a file first. Needs the upload key.
$router->post('/letter', [LetterController::class, 'update'])->middleware(['throttle:20,1', 'noindex'])->name('letter');

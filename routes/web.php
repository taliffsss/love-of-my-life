<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Dashboard\OrdersController;
use App\Http\Controllers\Dashboard\PagesController;
use App\Http\Controllers\Dashboard\PhotosController;
use App\Http\Controllers\Dashboard\SubdomainController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SiteController;
use Naluz\Routing\Router;

/** @var Router $router */

// ── The product ──────────────────────────────────────────────────────────────
$router->get('/', [LandingController::class, 'index'])->name('home');
$router->get('/demo', [LandingController::class, 'demo'])->name('demo');
$router->get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
$router->get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
$router->get('/manifest.webmanifest', [SeoController::class, 'manifest'])->name('manifest');

$router->get('/register', [RegisterController::class, 'show'])->middleware(['guest'])->name('register');
$router->post('/register', [RegisterController::class, 'store'])->middleware(['throttle:6,1', 'guest']);
$router->get('/login', [SessionController::class, 'show'])->middleware(['guest'])->name('login');
$router->post('/login', [SessionController::class, 'store'])->middleware(['throttle:10,1', 'guest']);
$router->post('/logout', [SessionController::class, 'destroy'])->middleware(['auth'])->name('logout');

// ── Dashboard (signed in; every page and order is looked up within the owner's own) ─
$slug = '[a-z2-9]{4,16}';
$router->group(['prefix' => 'dashboard', 'middleware' => ['auth', 'noindex']], static function (Router $router) use ($slug): void {
    $router->get('/', [PagesController::class, 'index'])->name('dashboard');
    $router->post('/pages', [PagesController::class, 'store'])->middleware(['throttle:20,1']);
    $router->get('/pages/{slug}', [PagesController::class, 'edit'])->where('slug', $slug);
    $router->put('/pages/{slug}/message', [PagesController::class, 'updateMessage'])->where('slug', $slug);
    $router->put('/pages/{slug}/song', [PagesController::class, 'updateSong'])->where('slug', $slug)->middleware(['throttle:20,1']);
    $router->put('/pages/{slug}/lock', [PagesController::class, 'updateGate'])->where('slug', $slug);
    $router->put('/pages/{slug}/design', [PagesController::class, 'updateDesign'])->where('slug', $slug);
    $router->get('/pages/{slug}/preview', [PagesController::class, 'preview'])->where('slug', $slug);
    // no file extension: nginx answers *.svg (and other static types) itself, so /qr.svg would never reach PHP
    $router->get('/pages/{slug}/qr', [PagesController::class, 'qr'])->where('slug', $slug);
    $router->delete('/pages/{slug}', [PagesController::class, 'destroy'])->where('slug', $slug);

    $router->post('/pages/{slug}/photos', [PhotosController::class, 'store'])->where('slug', $slug)->middleware(['throttle:60,1']);
    $router->post('/pages/{slug}/photos/order', [PhotosController::class, 'reorder'])->where('slug', $slug);
    $router->delete('/pages/{slug}/photos/{photo}', [PhotosController::class, 'destroy'])->where('slug', $slug)->where('photo', '\d+');

    $router->get('/subdomains/check', [SubdomainController::class, 'check'])->middleware(['throttle:60,1']);
    $router->put('/pages/{slug}/subdomain', [SubdomainController::class, 'update'])->where('slug', $slug);
    $router->delete('/pages/{slug}/subdomain', [SubdomainController::class, 'destroy'])->where('slug', $slug);

    $router->post('/pages/{slug}/orders', [OrdersController::class, 'store'])->where('slug', $slug)->middleware(['throttle:20,1']);
    $router->get('/orders/{reference}', [OrdersController::class, 'show'])->where('reference', 'LM-[A-Z0-9]{4}-[A-Z0-9]{4}');
    $router->post('/orders/{reference}/fake-pay', [OrdersController::class, 'fakePay'])->where('reference', 'LM-[A-Z0-9]{4}-[A-Z0-9]{4}');
});

// ── Public pages (also reached as <name>.<saas domain>/ through App\Http\Middleware\ResolveSiteHost) ─
$router->get('/p/{slug}', [SiteController::class, 'show'])->where('slug', $slug);
$router->post('/p/{slug}/unlock', [SiteController::class, 'unlock'])->where('slug', $slug)->middleware(['throttle:10,1', 'noindex']);
$router->get('/p/{slug}/robots.txt', [SiteController::class, 'robotsTxt'])->where('slug', $slug);
$router->get('/p/{slug}/sitemap.xml', [SiteController::class, 'sitemap'])->where('slug', $slug);

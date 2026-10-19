<?php

declare(strict_types=1);

use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\WebhooksController;
use Naluz\Routing\Router;

/** @var Router $router  — mounted under /api, stateless, rate limited */
$router->get('/ping', [StatusController::class, 'ping'])->name('ping');

// Payment providers call these (no session, no CSRF); each checks its own signature or token. See App\Services\Payments.
$router->post('/webhooks/paymongo', [WebhooksController::class, 'paymongo'])->name('webhooks.paymongo');
$router->post('/webhooks/xendit', [WebhooksController::class, 'xendit'])->name('webhooks.xendit');

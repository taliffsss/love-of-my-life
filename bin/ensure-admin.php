#!/usr/bin/env php
<?php

/**
 * Creates the administrator from ADMIN_EMAIL and ADMIN_PASSWORD when no account has that e-mail yet; does nothing otherwise.
 * Runs at container start (deploy/entrypoint.sh) after the migrations; safe to run any time.
 *
 *   docker exec $(docker ps -q -f name=loveofmylife_web) php bin/ensure-admin.php
 */

declare(strict_types=1);

use App\Services\AdminBootstrap;

if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line.');
}

$app = require __DIR__ . '/../bootstrap/app.php';
$app->registerErrorHandler();
$app->boot();

try {
    $result = $app->make(AdminBootstrap::class)->ensure((string) env('ADMIN_EMAIL', ''), (string) env('ADMIN_PASSWORD', ''));
} catch (\Throwable $e) {
    fwrite(STDERR, 'Administrator not created: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
echo match ($result) {
    'created' => 'Administrator created.',
    'exists' => 'The administrator account already exists: left as it is.',
    default => 'ADMIN_EMAIL / ADMIN_PASSWORD not set: no administrator created.',
} . PHP_EOL;

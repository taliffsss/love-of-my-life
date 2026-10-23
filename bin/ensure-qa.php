#!/usr/bin/env php
<?php

/**
 * Creates a normal account for testing from QA_EMAIL and QA_PASSWORD when no account has that e-mail yet; does nothing otherwise.
 * Runs at container start (deploy/entrypoint.sh) after the migrations; safe to run any time.
 *
 *   docker exec $(docker ps -q -f name=loveofmylife_web) php bin/ensure-qa.php
 */

declare(strict_types=1);

use App\Services\QaBootstrap;

if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line.');
}

$app = require __DIR__ . '/../bootstrap/app.php';
$app->registerErrorHandler();
$app->boot();

try {
    $result = $app->make(QaBootstrap::class)->ensure((string) env('QA_EMAIL', ''), (string) env('QA_PASSWORD', ''));
} catch (\Throwable $e) {
    fwrite(STDERR, 'QA account not created: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
echo match ($result) {
    'created' => 'QA account created.',
    'exists' => 'The QA account already exists: left as it is.',
    default => 'QA_EMAIL / QA_PASSWORD not set: no QA account created.',
} . PHP_EOL;

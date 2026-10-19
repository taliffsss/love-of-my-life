#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Seals the payment data in the database again with the current APP_KEY, and rebuilds its blind indexes.
 *
 * Use it after rotating APP_KEY:
 *   1. put the OLD key in APP_PREVIOUS_KEYS (comma separated) and the new one in APP_KEY;
 *   2. run `php bin/reseal-payments.php`;
 *   3. once it reports success, the old key may be removed from APP_PREVIOUS_KEYS.
 * Until step 2 is done, rows written with the old key are still read (through APP_PREVIOUS_KEYS) but their blind indexes no
 * longer match, so a webhook may not find its payment: do it right away. Safe to run twice. Stops without changing a row it
 * cannot read.
 *
 * What is sealed, and why: App\Services\PaymentVault.
 */

use App\Services\PaymentVault;
use Naluz\Database\Connection;

if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line.');
}

$app = require __DIR__ . '/../bootstrap/app.php';
$app->registerErrorHandler();
$app->boot();

$db = $app->make(Connection::class);
try {
    $count = $db->transaction(static fn (): int => PaymentVault::sealAll($db, true));
} catch (\Throwable $e) {
    fwrite(STDERR, 'Nothing was changed: ' . $e->getMessage() . "\n");
    exit(1);
}
echo "Sealed {$count} payment value(s) with the current APP_KEY.\n";

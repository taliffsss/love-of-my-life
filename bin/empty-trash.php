#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Deletes from storage the files of deleted photos that could not be deleted at the time (storage unreachable, the
 * request cut short). They wait in the storage_trash table; a file another photo still uses is kept. Safe to run any
 * time and as often as you like, for example daily from cron:
 *
 *   docker exec $(docker ps -q -f name=jinelin_web) php bin/empty-trash.php
 */

use App\Services\PhotoStorage;

if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line.');
}

$app = require __DIR__ . '/../bootstrap/app.php';
$app->registerErrorHandler();
$app->boot();

$storage = $app->make(PhotoStorage::class);
$totals = $storage->emptyTrash();
fwrite(STDOUT, sprintf(
    "Deleted %d file(s); kept %d still used by another photo; %d could not be deleted and stay in the trash.\n",
    $totals['deleted'],
    $totals['shared'],
    $totals['failed'],
));
exit($totals['failed'] > 0 ? 1 : 0);

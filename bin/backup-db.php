#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * A consistent copy of the SQLite database (accounts, pages, photos, orders), safe to take while the site runs:
 *
 *   php bin/backup-db.php            → storage/backups/database-20261013-120000.sqlite
 *
 * Copy the file off the server afterwards. Only for DB_CONNECTION=sqlite; use your database's own tools otherwise.
 */

use Naluz\Database\Connection;

if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line.');
}

$app = require __DIR__ . '/../bootstrap/app.php';
$app->registerErrorHandler();
$app->boot();

$db = $app->make(Connection::class);
if ($db->driver() !== 'sqlite') {
    fwrite(STDERR, "The database is not SQLite ({$db->driver()}): use its own backup tools.\n");
    exit(1);
}

$dir = storage_path('backups');
if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
    fwrite(STDERR, "Cannot create {$dir}.\n");
    exit(1);
}
$target = $dir . '/database-' . gmdate('Ymd-His') . '.sqlite';
// VACUUM INTO writes a complete, consistent copy even while other connections are writing
$db->statement('VACUUM INTO ' . $db->pdo()->quote($target));
fwrite(STDOUT, $target . ' (' . number_format((int) filesize($target)) . " bytes)\n");

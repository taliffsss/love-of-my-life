#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Copies the photos of public/photos to Spaces and lists them in storage/app/carousel.json, so they are served from
 * Spaces like every uploaded photo. Run it once on the server, where the SPACES_* settings are:
 *
 *   docker exec -it $(docker ps -q -f name=loveofmylife_web) php bin/migrate-photos.php [--dry-run]
 *
 * Safe to run again: photos already migrated are skipped. The files stay in public/photos, but the carousel no
 * longer shows a file once its copy is listed. The migrated photos come after every uploaded one, in filename order.
 */

use App\Services\CarouselStore;
use App\Services\PhotoCatalog;

if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line.');
}

$app = require __DIR__ . '/../bootstrap/app.php';
$app->registerErrorHandler();
$app->boot();

$dry = in_array('--dry-run', $argv, true);
$types = ['jpg' => 1, 'jpeg' => 1, 'png' => 1, 'gif' => 1, 'webp' => 1, 'avif' => 1];

$files = array_values(array_filter(
    scandir(base_path('public/photos')) ?: [],
    static fn (string $f): bool => isset($types[strtolower(pathinfo($f, PATHINFO_EXTENSION))]),
));
natcasesort($files);
$files = array_values($files);
if ($files === []) {
    fwrite(STDOUT, "No photos in public/photos.\n");
    exit(0);
}

try {
    $disk = storage('spaces');
} catch (InvalidArgumentException) {
    fwrite(STDERR, "Photo storage is not configured: set the SPACES_* values in .env first.\n");
    exit(1);
}

$folder = trim((string) config('filesystems.disks.spaces.folder'), '/');
$store = new CarouselStore(storage_path('app/carousel.json'), base_path('database/carousel.seed.json'));
$known = array_column($store->all(), 'key');

$entries = [];
foreach ($files as $file) {
    $key = ($folder !== '' ? $folder . '/' : '') . PhotoCatalog::MIGRATED . $file;
    if (in_array($key, $known, true)) {
        fwrite(STDOUT, "skip    $file (already migrated)\n");
        continue;
    }
    if ($dry) {
        fwrite(STDOUT, "would upload $file -> $key\n");
        continue;
    }
    try {
        $disk->put($key, (string) file_get_contents(base_path('public/photos/' . $file)));
    } catch (Throwable $e) {
        fwrite(STDERR, "FAILED  $file: " . $e->getMessage() . "\n");
        exit(1);
    }
    $entries[] = ['key' => $key, 'url' => $disk->url($key)];
    fwrite(STDOUT, "uploaded $file -> " . $disk->url($key) . "\n");
}

if (!$dry && $entries !== []) {
    // The store lists oldest first and the carousel shows newest first, so the first file goes last in the list.
    $added = $store->prepend(array_reverse($entries));
    fwrite(STDOUT, "$added photo(s) added to storage/app/carousel.json.\n");
}
fwrite(STDOUT, $dry ? "Dry run: nothing was uploaded.\n" : "Done.\n");

#!/usr/bin/env php
<?php

/**
 * Deleting is soft: an account, a page or a photo that is deleted is hidden (`deleted_at`), its row and file stay. This lists what was deleted and
 * brings it back, and, only for a privacy request, erases what is already deleted for good.
 *
 *   php bin/trash.php list
 *   php bin/trash.php restore user|page|photo <id>      # a user comes back with the pages and photos deleted together with it; a page with its photos
 *   php bin/trash.php erase   user|page|photo <id> --yes   # permanent; only for something that is already deleted; paid orders always stay
 *
 * In production: docker exec $(docker ps -q -f name=loveofmylife_web) php bin/trash.php list
 */

declare(strict_types=1);

use App\Models\Page;
use App\Models\Photo;
use App\Models\User;
use App\Services\AccountRemoval;
use App\Services\Trash;

if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line.');
}

$app = require __DIR__ . '/../bootstrap/app.php';
$app->registerErrorHandler();
$app->boot();

$say = static fn (string $line) => fwrite(STDOUT, $line . "\n");
$fail = static function (string $line): never {
    fwrite(STDERR, $line . "\n");
    exit(1);
};

[$script, $action, $kind, $id] = array_pad($argv, 4, null);
$yes = in_array('--yes', $argv, true);

if ($action === 'list' || $action === null) {
    foreach (User::query()->onlyTrashed()->orderBy('id')->get() as $u) {
        $say(sprintf('user  #%d  %s <%s>  deleted %s', $u->id, $u->name, $u->email, $u->getRawAttributes()['deleted_at']));
    }
    foreach (Page::query()->onlyTrashed()->orderBy('id')->get() as $p) {
        $say(sprintf('page  #%d  for %s (user #%d, /p/%s)  deleted %s', $p->id, $p->recipient, $p->user_id, $p->slug, $p->getRawAttributes()['deleted_at']));
    }
    foreach (Photo::query()->onlyTrashed()->orderBy('id')->get() as $ph) {
        $say(sprintf('photo #%d  page #%d  deleted %s', $ph->id, $ph->page_id, $ph->getRawAttributes()['deleted_at']));
    }
    exit(0);
}

if (!in_array($action, ['restore', 'erase'], true) || !in_array($kind, ['user', 'page', 'photo'], true) || !is_string($id) || !ctype_digit($id)) {
    $fail('Usage: php bin/trash.php list | restore|erase user|page|photo <id> [--yes]');
}
$id = (int) $id;
$removal = $app->make(AccountRemoval::class);
$trash = $app->make(Trash::class);

try {
    if ($kind === 'user') {
        $user = User::query()->onlyTrashed()->find($id) ?? $fail("No deleted user #$id.");
        if ($action === 'restore') {
            $n = $removal->restore($user);
            $say("Restored user #$id with {$n['pages']} page(s) and {$n['photos']} photo(s).");
        } else {
            $yes || $fail("This erases user #$id for good. Run again with --yes.");
            $n = $removal->erase($user);
            $say("Erased user #$id with {$n['pages']} page(s) and {$n['photos']} photo(s).");
        }
    } elseif ($kind === 'page') {
        $page = Page::query()->onlyTrashed()->find($id) ?? $fail("No deleted page #$id.");
        if ($action === 'restore') {
            $n = $trash->restorePage($page);
            $say("Restored page #$id (/p/{$page->slug}) with $n photo(s) deleted with it. Its subscription stays ended.");
        } else {
            $yes || $fail("This erases page #$id and its photos for good. Run again with --yes.");
            $say("Erased page #$id with " . $trash->erasePage($page) . ' photo(s). Paid orders stay on record.');
        }
    } else {
        $photo = Photo::query()->onlyTrashed()->find($id) ?? $fail("No deleted photo #$id.");
        if ($action === 'restore') {
            $trash->restorePhoto($photo);
            $say("Restored photo #$id.");
        } else {
            $yes || $fail("This erases photo #$id and its file for good. Run again with --yes.");
            $trash->erasePhoto($photo);
            $say("Erased photo #$id.");
        }
    }
} catch (\DomainException $e) {
    $fail($e->getMessage());
}

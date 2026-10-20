#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Moves the original single-page site into the product: creates (or reuses) the owner's account and a Forever page with
 * the same letter, song, photos and lock-screen answer, served on the original domain. Run it once on the server:
 *
 *   php bin/import-legacy.php --email=you@example.com --domain=old-site.example.com --answer=yam [--name="Your Name"]
 *
 * A new account gets a random password, printed once: sign in with it and change nothing else. Refuses to run twice for
 * the same domain.
 */

use App\Models\User;
use App\Services\LegacyImporter;
use App\Services\Sites;

if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line.');
}

$app = require __DIR__ . '/../bootstrap/app.php';
$app->registerErrorHandler();
$app->boot();

$options = getopt('', ['email:', 'domain:', 'answer:', 'name::']);
$email = User::normaliseEmail((string) ($options['email'] ?? ''));
$domain = (string) ($options['domain'] ?? '');
$answer = (string) ($options['answer'] ?? '');
if ($email === '' || $domain === '' || $answer === '') {
    fwrite(STDERR, "Usage: php bin/import-legacy.php --email=you@example.com --domain=old.example.com --answer=<lock screen answer> [--name=\"Your Name\"]\n");
    exit(2);
}

// a host under SAAS_DOMAIN is routed as a page subdomain, never as a custom domain
$saasDomain = $app->make(Sites::class)->domain();
$host = strtolower(trim($domain));
if ($host === $saasDomain || str_ends_with($host, '.' . $saasDomain)) {
    fwrite(STDERR, "[{$host}] is the product's own domain or one of its page addresses; give the site's old domain.\n");
    exit(2);
}

$user = User::query()->where('email', $email)->first();
$password = null;
if (!$user instanceof User) {
    $password = bin2hex(random_bytes(8));
    $user = new User();
    $user->forceFill([
        'name' => (string) ($options['name'] ?? strstr($email, '@', true)),
        'email' => $email,
        'password' => $password,
        'is_admin' => false,
    ])->save();
}

try {
    $page = $app->make(LegacyImporter::class)->import($user, $domain, $answer);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

$photos = App\Models\Photo::query()->where('page_id', $page->id)->count();
fwrite(STDOUT, "Imported the page for {$page->recipient} with {$photos} photo(s).\n");
fwrite(STDOUT, 'Served on:   https://' . $page->custom_domain . "/\n");
fwrite(STDOUT, 'Also at:     ' . $app->make(Sites::class)->pathUrl($page) . "\n");
fwrite(STDOUT, "Owner:       {$user->email}\n");
if ($password !== null) {
    fwrite(STDOUT, "Password:    {$password}   (shown once)\n");
}

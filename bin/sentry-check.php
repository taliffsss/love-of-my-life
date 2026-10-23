#!/usr/bin/env php
<?php

/**
 * Checks Sentry from where the app runs: which settings it sees (the DSN is masked), then sends one event and one log line with
 * Sentry's own debug output, so the answer from Sentry (200 = accepted) or the reason for a failure is printed.
 *
 *   docker exec $(docker ps -q -f name=loveofmylife_web) php bin/sentry-check.php
 */

declare(strict_types=1);

use App\Support\ErrorReporting;

if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line.');
}

$app = require __DIR__ . '/../bootstrap/app.php';
$app->boot();

$config = (array) config('sentry', []);
$dsn = (string) ($config['dsn'] ?? '');
echo 'APP_ENV: ', config('app.env'), PHP_EOL;
echo 'DSN: ', $dsn === '' ? 'NOT SET (set SENTRY_DSN or SENTRY_NALUZ_DSN)' : preg_replace('~//[^@]*@~', '//***@', $dsn), PHP_EOL;
echo 'enable_logs: ', var_export((bool) ($config['enable_logs'] ?? false), true), '   traces: ', $config['traces_sample_rate'] ?? 0, PHP_EOL;
echo 'log channel: ', config('logging.default'), ' [', implode(',', (array) config('logging.channels.stack.channels', [])), '] level ', getenv('LOG_LEVEL') ?: '(default)', PHP_EOL;
echo 'curl: ', extension_loaded('curl') ? 'yes' : 'NO (Sentry cannot send without it)', PHP_EOL;
if ($dsn === '') {
    exit(1);
}

ErrorReporting::start($config + ['debug' => true]);
\Sentry\captureMessage('Sentry check from ' . gethostname() . ' (' . config('app.env') . ')');
\Sentry\logger()->error('Sentry log check from ' . gethostname());
\Sentry\logger()->flush();
echo 'done: look for "Result: success (status: 200)" above for the event and for the log.', PHP_EOL;

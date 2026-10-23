<?php

declare(strict_types=1);

// Error reporting to Sentry (https://sentry.io). Off while there is no DSN: SENTRY_DSN, or SENTRY_NALUZ_DSN when that is the name used.
// Server errors (HTTP 5xx) and uncaught errors are sent with the request's method and path; cookies, headers, request bodies and the
// arguments of stack frames (which would carry passwords, payment keys and card data) are never sent.
return [
    'dsn' => (string) (env('SENTRY_DSN') ?: env('SENTRY_NALUZ_DSN', '')),
    'environment' => (string) env('SENTRY_ENVIRONMENT', (string) env('APP_ENV', 'production')),
    'release' => (string) env('SENTRY_RELEASE', ''),
    // 1.0 traces every request; lower it on a busy site (it counts against the Sentry quota)
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0),
    // relative to the traces rate; profiling needs PHP's excimer extension, and is skipped without it
    'profiles_sample_rate' => (float) env('SENTRY_PROFILES_SAMPLE_RATE', 0),
    // send the application log to Sentry too: add the channel `sentry_logs` to the stack (LOG_CHANNEL=stack, LOG_STACK=single,sentry_logs)
    'enable_logs' => filter_var(env('SENTRY_ENABLE_LOGS', false), FILTER_VALIDATE_BOOLEAN),
];

<?php

declare(strict_types=1);

return [
    // Where records go unless you ask for another channel: logger()->channel('slack')->critical(...)
    'default' => env('LOG_CHANNEL', 'daily'),

    'channels' => [
        // storage/logs/naluz-YYYY-MM-DD.log, old files deleted after `days`
        'daily' => ['driver' => 'daily', 'level' => env('LOG_LEVEL', 'debug'), 'days' => (int) env('LOG_DAYS', 14)],

        // one file: storage/logs/naluz.log
        'single' => ['driver' => 'single', 'level' => env('LOG_LEVEL', 'debug'), 'file' => 'naluz.log'],

        // containers / Kubernetes / systemd: let the platform collect it. 'format' => 'json' for log aggregators.
        'stderr' => ['driver' => 'stderr', 'level' => env('LOG_LEVEL', 'debug'), 'format' => 'line'],

        // PHP's own error log (web server / php-fpm)
        'errorlog' => ['driver' => 'errorlog', 'level' => env('LOG_LEVEL', 'debug')],

        // Slack incoming webhook; critical and above by default. Add `'include_trace' => true` only for private channels.
        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL', ''),
            'level' => env('LOG_SLACK_LEVEL', 'critical'),
            'username' => env('APP_NAME', 'NaluzPHP'),
            'emoji' => ':rotating_light:',
        ],

        // several at once — set LOG_CHANNEL=production to use it
        'production' => ['driver' => 'stack', 'channels' => ['daily', 'slack']],

        // LOG_CHANNEL=stack with LOG_STACK=single,sentry_logs writes the file and sends the log to Sentry (SENTRY_ENABLE_LOGS=true; without
        // it `sentry_logs` is a channel that does nothing, so naming it is always safe)
        'stack' => ['driver' => 'stack', 'channels' => array_values(array_filter(array_map('trim', explode(',', (string) env('LOG_STACK', 'single')))))],

        'null' => ['driver' => 'null'],

        // bring your own PSR-3 logger (Monolog, Sentry, …):
        // 'sentry' => ['driver' => 'custom', 'via' => fn (array $config) => new MyMonologFactory()],
    ],
];

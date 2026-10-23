<?php

declare(strict_types=1);

namespace App\Support;

/** Starts Sentry when SENTRY_DSN is set (config/sentry.php), and keeps anything private out of what it sends. */
final class ErrorReporting
{
    /** @param array<string,mixed> $config config('sentry') */
    public static function start(array $config): bool
    {
        $dsn = trim((string) ($config['dsn'] ?? ''));
        if ($dsn === '' || !function_exists('Sentry\\init')) {
            return false;
        }
        \Sentry\init(array_filter([
            'dsn' => $dsn,
            'environment' => (string) ($config['environment'] ?? ''),
            'release' => (string) ($config['release'] ?? '') ?: null,
            'traces_sample_rate' => (float) ($config['traces_sample_rate'] ?? 0),
            'profiles_sample_rate' => extension_loaded('excimer') ? (float) ($config['profiles_sample_rate'] ?? 0) : null,
            'enable_logs' => !empty($config['enable_logs']) ?: null,
            'send_default_pii' => false,
            'max_request_body_size' => 'none',
            'before_send' => static fn (\Sentry\Event $event): ?\Sentry\Event => self::scrub($event),
        ], static fn ($v): bool => $v !== null && $v !== ''));
        return true;
    }

    /** Takes out what must never leave the server: stack-frame arguments, cookies, headers, bodies and query strings. */
    public static function scrub(\Sentry\Event $event): \Sentry\Event
    {
        foreach ($event->getExceptions() as $exception) {
            $trace = $exception->getStacktrace();
            foreach ($trace?->getFrames() ?? [] as $frame) {
                $frame->setVars([]);
            }
        }
        $request = $event->getRequest();
        unset($request['cookies'], $request['headers'], $request['data'], $request['query_string']);
        $event->setRequest($request);
        return $event;
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

use Psr\Log\AbstractLogger;

/**
 * The `sentry_logs` log channel: sends each record to Sentry's logs. The message is sent with its {placeholders} filled in from the
 * context; objects (an exception, a request) and everything else that is not plain text or a number stay out. Sent in one batch when
 * the request ends.
 */
final class SentryLogger extends AbstractLogger
{
    private const LEVELS = ['debug' => 'debug', 'info' => 'info', 'notice' => 'info', 'warning' => 'warn', 'error' => 'error', 'critical' => 'fatal', 'alert' => 'fatal', 'emergency' => 'fatal'];
    private bool $flushing = false;

    /** @param array<mixed> $context */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $method = self::LEVELS[(string) $level] ?? 'info';
        $replace = [];
        foreach ($context as $key => $value) {
            if (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
                $replace['{' . $key . '}'] = (string) $value;
            }
        }
        try {
            \Sentry\logger()->{$method}(strtr((string) $message, $replace));
            if (!$this->flushing) {
                $this->flushing = true;
                register_shutdown_function(static function (): void {
                    \Sentry\logger()->flush();
                });
            }
        } catch (\Throwable) {
            // logging must never break the request
        }
    }
}

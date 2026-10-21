<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The 6-digit codes e-mailed to confirm an address or reset a password. A code lives 15 minutes, allows 5 wrong guesses (then it is
 * void and a new one must be asked for), and a new one cannot be sent for a minute. Only a keyed hash is stored (HMAC-SHA256 with the
 * app key, the account and the purpose), so a copy of the database does not reveal a live code, and a code for one purpose or account
 * is useless for another.
 */
final class OneTimeCode
{
    public const TTL = 900;
    public const MAX_ATTEMPTS = 5;
    public const RESEND_AFTER = 60;

    public static function make(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public static function hash(string $code, int $userId, string $purpose): string
    {
        return hash_hmac('sha256', $purpose . ':' . $userId . ':' . $code, (string) config('app.key'));
    }

    public static function matches(string $code, ?string $stored, int $userId, string $purpose): bool
    {
        return $stored !== null && $stored !== '' && hash_equals($stored, self::hash(trim($code), $userId, $purpose));
    }

    /** What someone types may have spaces or a dash in it ("123 456"). */
    public static function clean(string $typed): string
    {
        return preg_replace('/\D+/', '', $typed) ?? '';
    }

    public static function canResend(?\DateTimeInterface $sentAt, ?\DateTimeImmutable $now = null): bool
    {
        return $sentAt === null || ($now ?? new \DateTimeImmutable())->getTimestamp() - $sentAt->getTimestamp() >= self::RESEND_AFTER;
    }
}

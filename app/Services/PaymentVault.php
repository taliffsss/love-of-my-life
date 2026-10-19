<?php

declare(strict_types=1);

namespace App\Services;

use Naluz\Database\Connection;
use Naluz\Security\DecryptException;
use Naluz\Security\Encrypter;

/**
 * Keeps what identifies a payment at the provider (payment, subscription, invoice and customer ids, and the pages that
 * approve a payment) encrypted in the database, and readable again inside the app.
 *
 * - Encryption is the framework's authenticated XChaCha20-Poly1305 with APP_KEY; the models decrypt on read (`encrypted`
 *   cast), so the rest of the code only ever sees the plain value.
 * - An encrypted value cannot be searched or made unique, and a webhook has to find its payment by the provider's id. So
 *   a provider id also gets a *blind index*: an HMAC of it under a key derived from APP_KEY. Equal ids give equal indexes
 *   (look-ups and uniqueness work), and the index reveals nothing without the key.
 * - Rows written by raw SQL (Billing::markPaid) seal their values with seal() and index().
 *
 * The sealed columns: orders.provider_ref, orders.checkout_url; payment_attempts.provider_ref, redirect_url,
 * subscription_ref; subscriptions.provider_ref, last_invoice_ref; provider_refs.ref. Card numbers and phone numbers are never
 * stored at all. Rotating APP_KEY: put the old key in APP_PREVIOUS_KEYS and run `php bin/reseal-payments.php`.
 */
final class PaymentVault
{
    /** table => [sealed columns]; a column also listed in INDEXED gets a `<column>_idx` blind index beside it */
    public const SEALED = [
        'orders' => ['provider_ref', 'checkout_url'],
        'payment_attempts' => ['provider_ref', 'redirect_url', 'subscription_ref'],
        'subscriptions' => ['provider_ref', 'last_invoice_ref'],
        'provider_refs' => ['ref'],
    ];

    /** table => [columns that are looked up by value] */
    public const INDEXED = [
        'orders' => ['provider_ref'],
        'payment_attempts' => ['provider_ref'],
    ];

    private static function encrypter(): Encrypter
    {
        return app(Encrypter::class);
    }

    /** The encrypted form of a value, or null for none. */
    public static function seal(?string $value): ?string
    {
        return $value === null || $value === '' ? null : self::encrypter()->encryptString($value);
    }

    /** The plain value of a sealed one, or null for none. @throws DecryptException when it is not a value sealed with a key of this app */
    public static function open(?string $sealed): ?string
    {
        return $sealed === null || $sealed === '' ? null : self::encrypter()->decryptString($sealed);
    }

    /** The blind index of a value (64 hex characters), or null for none. */
    public static function index(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $key = (string) config('app.key', '');
        $raw = str_starts_with($key, 'base64:') ? (string) base64_decode(substr($key, 7), true) : $key;
        if ($raw === '') {
            throw new \RuntimeException('No application key set. Run `php naluz key:generate`.');
        }
        return hash_hmac('sha256', $value, hash_hmac('sha256', 'payment-blind-index', $raw, true));
    }

    /**
     * Seals every payment value in the database that is not sealed yet, and (re)builds the blind indexes. Run by the migration
     * that introduced sealing, and by bin/reseal-payments.php after a key rotation (with $all: values sealed with an older key
     * are read with APP_PREVIOUS_KEYS and sealed again with the current one).
     *
     * @return int how many values were sealed
     */
    public static function sealAll(Connection $db, bool $all = false): int
    {
        $count = 0;
        foreach (self::SEALED as $table => $columns) {
            foreach ($db->table($table)->select('id', ...$columns)->get() as $row) {
                $update = [];
                foreach ($columns as $column) {
                    $stored = $row[$column] ?? null;
                    if ($stored === null || $stored === '') {
                        continue;
                    }
                    $plain = self::plain((string) $stored);
                    if ($plain['was_sealed'] && !$all) {
                        continue;
                    }
                    $update[$column] = self::seal($plain['value']);
                    if (in_array($column, self::INDEXED[$table] ?? [], true)) {
                        $update[$column . '_idx'] = self::index($plain['value']);
                    }
                    $count++;
                }
                if ($update !== []) {
                    $db->table($table)->where('id', '=', $row['id'])->update($update);
                }
            }
        }
        return $count;
    }

    /** @return array{value:string,was_sealed:bool} the plain value of a stored one (which may be an old plain value, from before sealing) */
    private static function plain(string $stored): array
    {
        try {
            return ['value' => self::encrypter()->decryptString($stored), 'was_sealed' => true];
        } catch (DecryptException $e) {
            // a sealed value this app's keys cannot read must stop the run, never be taken for a plain value and sealed again
            if (preg_match('/^[A-Za-z0-9_-]{56,}$/', $stored) === 1) {
                throw new \RuntimeException('A sealed payment value cannot be read with APP_KEY or APP_PREVIOUS_KEYS: the key that sealed it is missing.', 0, $e);
            }
            return ['value' => $stored, 'was_sealed' => false];
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\PaymentVault;
use Naluz\Database\Orm\Model;

/**
 * One payment started for an order. `provider_ref` is the provider's id for it (PayMongo: the payment intent).
 *
 * @property int $id
 * @property int $order_id
 * @property string $provider
 * @property string $provider_ref
 * @property string $method
 * @property string $billing
 * @property ?string $redirect_url where an e-wallet payment is approved
 * @property ?string $subscription_ref the provider's subscription this is the first payment of
 * @property ?\DateTimeImmutable $expires_at
 */
final class PaymentAttempt extends Model
{
    // encrypted in the database (App\Services\PaymentVault); provider_ref also has a blind index to look a payment up by
    protected array $casts = [
        'order_id' => 'integer', 'expires_at' => 'datetime', 'created_at' => 'datetime',
        'provider_ref' => 'encrypted', 'redirect_url' => 'encrypted', 'subscription_ref' => 'encrypted',
    ];

    public function setProviderRefAttribute(mixed $value): void
    {
        $plain = $value === null || $value === '' ? null : (string) $value;
        $this->setRawAttribute('provider_ref', PaymentVault::seal($plain));
        $this->setRawAttribute('provider_ref_idx', PaymentVault::index($plain));
    }

    /** The attempt of this provider with this id, or null. */
    public static function withProviderRef(string $provider, string $ref): ?self
    {
        $attempt = self::query()->withoutCache()->useWritePdo()->where('provider', $provider)->where('provider_ref_idx', PaymentVault::index($ref))->first();
        return $attempt instanceof self ? $attempt : null;
    }


    public function expired(?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return $this->expires_at !== null && $this->expires_at <= $now;
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Naluz\Database\Orm\Model;

/**
 * An id the payment provider gave us that is made once and reused: a customer for a user (`customer`, key "user:7"), a plan
 * for a price (`plan`, key "sweetheart:month:35000").
 *
 * @property string $provider
 * @property string $kind
 * @property string $ref_key
 * @property string $ref
 */
final class ProviderRef extends Model
{
    // encrypted in the database (App\Services\PaymentVault)
    protected array $casts = ['ref' => 'encrypted'];

    public static function lookup(string $provider, string $kind, string $key): ?string
    {
        $row = self::query()->withoutCache()->useWritePdo()->where('provider', $provider)->where('kind', $kind)->where('ref_key', $key)->first();
        return $row instanceof self ? $row->ref : null;
    }

    public static function remember(string $provider, string $kind, string $key, string $ref): void
    {
        $row = new self();
        $row->forceFill(['provider' => $provider, 'kind' => $kind, 'ref_key' => $key, 'ref' => $ref])->save();
    }
}

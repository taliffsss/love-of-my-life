<?php

declare(strict_types=1);

namespace App\Models;

use Naluz\Database\Orm\Model;

/**
 * A plan that keeps renewing for a page. Status: `active`, `cancelled` (the customer stopped it) or `ended` (the page was
 * deleted or moved to a higher plan). Mode: `manual` (a renewal order the customer pays again) or `auto` (the provider charges the customer).
 *
 * @property int $id
 * @property int $user_id
 * @property ?int $page_id
 * @property string $plan
 * @property string $period
 * @property int $amount
 * @property string $currency
 * @property string $method
 * @property string $provider
 * @property string $mode
 * @property ?string $provider_ref the provider's subscription (auto mode)
 * @property ?string $last_invoice_ref the last provider invoice already counted
 * @property string $status
 * @property ?\DateTimeImmutable $cancelled_at
 */
final class Subscription extends Model
{
    public const ACTIVE = 'active';
    public const CANCELLED = 'cancelled';
    public const ENDED = 'ended';

    // the provider's ids are encrypted in the database (App\Services\PaymentVault)
    protected array $casts = ['user_id' => 'integer', 'page_id' => 'integer', 'amount' => 'integer', 'cancelled_at' => 'datetime', 'provider_ref' => 'encrypted', 'last_invoice_ref' => 'encrypted'];

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }
}

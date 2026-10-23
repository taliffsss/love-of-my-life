<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\PaymentVault;
use Naluz\Database\Orm\Model;

/**
 * A plan bought for a page, or a photo pack for the account (plan `photos`; `photos` is how many it adds, `page_id`
 * the page it was bought from, if any). Amounts are in centavos. Status: pending → paid (or failed/cancelled).
 * Activation happens once, when the order turns paid (App\Services\Billing); a pack needs none, since paid packs are
 * counted where the allowance is worked out (App\Services\PhotoAllowance).
 *
 * @property int $id
 * @property string $reference
 * @property int $user_id
 * @property ?int $page_id
 * @property string $plan
 * @property string $period month | year | once (config/saas.php periods)
 * @property int $photos photos a pack adds; 0 for a plan
 * @property int $amount
 * @property string $currency
 * @property string $status
 * @property string $provider
 * @property ?string $provider_ref
 * @property ?\DateTimeImmutable $paid_at
 * @property ?string $method gcash, paymaya, qrph or card
 * @property string $billing one_time or subscription
 * @property ?int $subscription_id the subscription a renewal order belongs to
 * @property ?string $checkout_url the provider's hosted checkout for a pending order
 * @property ?\DateTimeImmutable $updated_at
 */
final class Order extends Model
{
    public const PENDING = 'pending';
    public const PAID = 'paid';
    public const CANCELLED = 'cancelled';
    public const STATUSES = [self::PENDING, self::PAID, self::CANCELLED];

    /** How a customer pays. A card is any Visa or Mastercard. */
    public const GCASH = 'gcash';
    public const PAYMAYA = 'paymaya';
    public const CARD = 'card';
    public const QRPH = 'qrph';
    public const METHODS = [self::GCASH => 'GCash', self::PAYMAYA => 'PayMaya', self::CARD => 'Credit card', self::QRPH => 'QR Ph'];

    public const ONE_TIME = 'one_time';
    public const SUBSCRIPTION = 'subscription';

    /** The `plan` of a photo pack order (never a key of config/saas.php plans). */
    public const PHOTO_PACK = 'photos';

    protected array $casts = [
        'user_id' => 'integer',
        'page_id' => 'integer',
        'amount' => 'integer',
        'photos' => 'integer',
        'subscription_id' => 'integer',
        // the provider's ids and pages for this payment are encrypted in the database (App\Services\PaymentVault)
        'provider_ref' => 'encrypted',
        'checkout_url' => 'encrypted',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** Sets the provider's id for this payment: sealed, with its blind index beside it (see PaymentVault). */
    public function setProviderRefAttribute(mixed $value): void
    {
        $plain = $value === null || $value === '' ? null : (string) $value;
        $this->setRawAttribute('provider_ref', PaymentVault::seal($plain));
        $this->setRawAttribute('provider_ref_idx', PaymentVault::index($plain));
    }

    /** The order that has this provider id, or null. */
    public static function withProviderRef(string $ref): ?self
    {
        $order = self::query()->withoutCache()->useWritePdo()->where('provider_ref_idx', PaymentVault::index($ref))->first();
        return $order instanceof self ? $order : null;
    }

    public function isPhotoPack(): bool
    {
        return $this->plan === self::PHOTO_PACK;
    }

    /** A short reference a person can read out or type in a payment note, e.g. LM-7KQ2-9XDA. */
    public static function newReference(): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // no 0/O, 1/I
        $out = '';
        for ($i = 0; $i < 8; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return 'LM-' . substr($out, 0, 4) . '-' . substr($out, 4);
    }
}

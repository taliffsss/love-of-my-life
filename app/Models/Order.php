<?php

declare(strict_types=1);

namespace App\Models;

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
 * @property ?string $checkout_url the provider's hosted checkout for a pending order
 * @property ?\DateTimeImmutable $updated_at
 */
final class Order extends Model
{
    public const PENDING = 'pending';
    public const PAID = 'paid';
    public const CANCELLED = 'cancelled';
    public const STATUSES = [self::PENDING, self::PAID, self::CANCELLED];

    /** The `plan` of a photo pack order (never a key of config/saas.php plans). */
    public const PHOTO_PACK = 'photos';

    protected array $casts = [
        'user_id' => 'integer',
        'page_id' => 'integer',
        'amount' => 'integer',
        'photos' => 'integer',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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

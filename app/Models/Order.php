<?php

declare(strict_types=1);

namespace App\Models;

use Naluz\Database\Orm\Model;

/**
 * A plan bought for a page. Amounts are in centavos. Status: pending → paid (or failed/cancelled). Activation happens
 * once, when the order turns paid (App\Services\Billing).
 *
 * @property int $id
 * @property string $reference
 * @property int $user_id
 * @property ?int $page_id
 * @property string $plan
 * @property string $period month | year | once (config/saas.php periods)
 * @property int $amount
 * @property string $currency
 * @property string $status
 * @property string $provider
 * @property ?string $provider_ref
 * @property ?\DateTimeImmutable $paid_at
 */
final class Order extends Model
{
    public const PENDING = 'pending';
    public const PAID = 'paid';
    public const CANCELLED = 'cancelled';

    protected array $casts = [
        'user_id' => 'integer',
        'page_id' => 'integer',
        'amount' => 'integer',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
    ];

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

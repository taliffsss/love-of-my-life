<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Order;

/**
 * No online checkout yet: the customer sees the amount, the order reference and how to pay; an admin confirms the
 * payment with `php bin/grant-plan.php <reference>`.
 */
final class ManualGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'manual';
    }

    public function checkoutUrl(Order $order): string
    {
        return '/dashboard/orders/' . $order->reference;
    }
}

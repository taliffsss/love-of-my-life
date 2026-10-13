<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Order;

/** Takes the customer from an order to wherever they pay (a hosted checkout, or instructions for a manual payment). */
interface PaymentGateway
{
    /** Stored on the order as its provider. */
    public function name(): string;

    /** Where to send the customer once the order exists. */
    public function checkoutUrl(Order $order): string;
}

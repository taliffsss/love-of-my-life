<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Order;

/**
 * Test checkout for development and staging: the order page shows a "pay" button that marks the order paid. Only the
 * environments listed below may use it, so a mistyped APP_ENV ("prod", "Production") can never hand out free plans.
 */
final class FakeGateway implements PaymentGateway
{
    public const ENVIRONMENTS = ['local', 'testing', 'development', 'staging'];

    public function __construct(private readonly string $environment)
    {
        if (!in_array($environment, self::ENVIRONMENTS, true)) {
            throw new \LogicException(
                "The fake payment gateway only runs with APP_ENV=" . implode('|', self::ENVIRONMENTS) . " (not [{$environment}]). Set PAYMENTS_DRIVER=none."
            );
        }
    }

    public function name(): string
    {
        return 'fake';
    }

    public function checkoutUrl(Order $order): string
    {
        return '/dashboard/orders/' . $order->reference;
    }
}

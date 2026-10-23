<?php

declare(strict_types=1);

namespace App\Services\Payments;

/**
 * The payment provider could not start a checkout. The message is safe to show to the customer. `providerDetail` is the provider's
 * own error ("code: detail"), for the logs and for the QA account only: it is never shown to a customer.
 */
final class PaymentException extends \RuntimeException
{
    public function __construct(string $message = '', public readonly string $providerDetail = '')
    {
        parent::__construct($message);
    }
}

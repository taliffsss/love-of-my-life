<?php

declare(strict_types=1);

namespace App\Services\Payments;

/** The payment provider could not start a checkout. The message is safe to show to the customer. */
final class PaymentException extends \RuntimeException
{
}

<?php

declare(strict_types=1);

namespace App\Services\Payments;

/** A webhook that is not from the provider (bad or missing signature/token) or cannot be read. */
final class InvalidWebhook extends \RuntimeException
{
}

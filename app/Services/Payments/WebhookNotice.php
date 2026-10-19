<?php

declare(strict_types=1);

namespace App\Services\Payments;

/** What a verified webhook says about one order. */
final class WebhookNotice
{
    public const PAID = 'paid';
    public const EXPIRED = 'expired';

    public function __construct(
        public readonly string $kind,
        /** Our order reference (LM-XXXX-XXXX) */
        public readonly string $reference,
        /** The provider's checkout session / invoice id */
        public readonly string $providerRef,
        /** In centavos; null when the event does not say */
        public readonly ?int $amount,
        public readonly ?string $currency,
    ) {
    }
}

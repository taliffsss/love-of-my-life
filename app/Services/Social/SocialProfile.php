<?php

declare(strict_types=1);

namespace App\Services\Social;

/** What a provider told us about the person who just signed in. */
final class SocialProfile
{
    public function __construct(
        public readonly string $provider,
        public readonly string $uid,
        public readonly string $name,
        public readonly string $email,
        /** Google says whether it vouches for the address; Facebook only ever returns one its owner confirmed. */
        public readonly bool $emailVerified,
    ) {
    }
}

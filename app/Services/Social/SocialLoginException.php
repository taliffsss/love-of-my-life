<?php

declare(strict_types=1);

namespace App\Services\Social;

/** A sign-in that cannot go on; the message is safe to show to the person. */
final class SocialLoginException extends \RuntimeException
{
}

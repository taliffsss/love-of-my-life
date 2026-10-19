<?php

declare(strict_types=1);

namespace Naluz\Tests\Unit;

use App\Support\Time;
use Naluz\Tests\TestCase;

final class TimeTest extends TestCase
{
    public function testStoredUtcIsShownInManilaTime(): void
    {
        $utc = new \DateTimeImmutable('2026-10-18 08:46:00', new \DateTimeZone('UTC'));
        $this->assertSame('Oct 18, 2026 4:46 PM', Time::show($utc, 'M j, Y g:i A'));
        // late evening UTC is already the next morning in Manila
        $this->assertSame('Oct 19, 2026', Time::show(new \DateTimeImmutable('2026-10-18 20:00:00', new \DateTimeZone('UTC'))));
        $this->assertSame('', Time::show(null));
        $this->assertSame('Manila (UTC+8)', Time::label());
    }

    public function testTheZoneCanBeChangedAndABadOneFallsBack(): void
    {
        $config = $this->app->make(\Naluz\Config\Repository::class);
        $config->set('saas.display_timezone', 'Asia/Tokyo');
        $this->assertSame('Tokyo (UTC+9)', Time::label());
        $config->set('saas.display_timezone', 'Nowhere/Land');
        $this->assertSame('Manila (UTC+8)', Time::label());
    }

    public function testADayStartsAtLocalMidnight(): void
    {
        $now = new \DateTimeImmutable('2026-10-18 20:00:00', new \DateTimeZone('UTC')); // Oct 19, 04:00 in Manila
        $this->assertSame('2026-10-18 16:00:00', Time::dayStartUtc(0, $now), 'Oct 19 00:00 Manila = Oct 18 16:00 UTC');
        $this->assertSame('2026-10-17 16:00:00', Time::dayStartUtc(1, $now));
    }
}

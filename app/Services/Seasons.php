<?php

declare(strict_types=1);

namespace App\Services;

/**
 * When the seasonal templates (Designs::LAYOUTS entries with `season`: Halloween, Christmas, Valentine) are offered: from `lead_days`
 * (two weeks) before their day until the day itself. Outside that window they are not shown on the landing page or in the design
 * picker and cannot be newly chosen; a page that already uses one keeps it and keeps working, and the /demo links still open.
 * `always` (SEASONS_ALWAYS=true) offers all of them all year, for testing.
 */
final class Seasons
{
    /**
     * @param array<string,array{0:int,1:int}> $events layout key => [month, day] of its day
     * @param ?\Closure():\DateTimeImmutable $clock now, for tests
     */
    public function __construct(
        private readonly array $events,
        private readonly int $leadDays = 14,
        private readonly bool $always = false,
        private readonly string $timezone = 'UTC',
        private readonly ?\Closure $clock = null,
    ) {
    }

    public function now(): \DateTimeImmutable
    {
        return $this->clock !== null ? ($this->clock)() : new \DateTimeImmutable('now', new \DateTimeZone($this->timezone));
    }

    public function isSeasonal(string $layout): bool
    {
        return isset($this->events[$layout]);
    }

    /** Whether the template is on offer now: every everyday template always, a seasonal one two weeks before its day. */
    public function isOpen(string $layout, ?\DateTimeImmutable $now = null): bool
    {
        if (!$this->isSeasonal($layout) || $this->always) {
            return true;
        }
        $today = ($now ?? $this->now())->setTime(0, 0);
        $day = $this->day($layout, (int) $today->format('Y'));
        return $today >= $day->modify("-{$this->leadDays} days") && $today <= $day;
    }

    /** The first day a closed seasonal template is offered again: "Halloween opens on 17 Oct". */
    public function opensOn(string $layout, ?\DateTimeImmutable $now = null): ?\DateTimeImmutable
    {
        if (!$this->isSeasonal($layout)) {
            return null;
        }
        $today = ($now ?? $this->now())->setTime(0, 0);
        $year = (int) $today->format('Y');
        $opens = $this->day($layout, $year)->modify("-{$this->leadDays} days");
        return $opens > $today ? $opens : $this->day($layout, $year + 1)->modify("-{$this->leadDays} days");
    }

    /**
     * The templates on offer: all but the seasonal ones that are closed.
     *
     * @param array<string,array<string,mixed>> $layouts Designs::LAYOUTS
     * @return array<string,array<string,mixed>>
     */
    public function offered(array $layouts, ?\DateTimeImmutable $now = null): array
    {
        $now ??= $this->now();
        return array_filter($layouts, fn (string $key): bool => $this->isOpen($key, $now), ARRAY_FILTER_USE_KEY);
    }

    private function day(string $layout, int $year): \DateTimeImmutable
    {
        [$month, $day] = $this->events[$layout];
        return (new \DateTimeImmutable('now', new \DateTimeZone($this->timezone)))->setDate($year, $month, $day)->setTime(0, 0);
    }
}

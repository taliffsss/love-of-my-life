<?php

declare(strict_types=1);

namespace App\Services;

/**
 * When the seasonal templates (Designs::LAYOUTS entries with `season`: Halloween, Christmas, Valentine) are offered: from `lead_days`
 * (two weeks) before their day until the day itself; an event may name its own number of days (Christmas does). Outside that window they are not shown on the landing page or in the design
 * picker and cannot be newly chosen; a page that already uses one keeps it and keeps working, and the /demo links still open.
 * `always` (SEASONS_ALWAYS=true) offers all of them all year, for testing.
 */
final class Seasons
{
    /**
     * @param array<string,array{0:int,1:int,2?:int}> $events layout key => [month, day] of its day, then its own lead days if not $leadDays
     * @param ?\Closure():\DateTimeImmutable $clock now, for tests
     * @param bool $preview whether ?theme= may show a look out of season (outside production, or THEME_PREVIEW=true)
     */
    public function __construct(
        private readonly array $events,
        private readonly int $leadDays = 14,
        private readonly bool $always = false,
        private readonly string $timezone = 'UTC',
        private readonly ?\Closure $clock = null,
        private readonly bool $preview = false,
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

    /** Whether the template is on offer now: every everyday template always, a seasonal one from its lead days before its day. */
    public function isOpen(string $layout, ?\DateTimeImmutable $now = null): bool
    {
        if (!$this->isSeasonal($layout) || $this->always) {
            return true;
        }
        $today = ($now ?? $this->now())->setTime(0, 0);
        $day = $this->day($layout, (int) $today->format('Y'));
        return $today >= $this->opening($layout, $day) && $today <= $day;
    }

    /**
     * The look of the landing page today: the season that is on (Halloween, Christmas or Valentine, which never overlap) or "default" the rest of the year.
     * The season's keys are the layout keys of its template, which is also its theme's name.
     */
    public function theme(?\DateTimeImmutable $now = null): string
    {
        $now ??= $this->now();
        foreach (array_keys($this->events) as $season) {
            if ($this->isOpen($season, $now)) {
                return $season;
            }
        }
        return 'default';
    }

    /**
     * The look a visitor sees: today's theme, or the one in ?theme= when previewing is allowed (outside production, or THEME_PREVIEW=true).
     *
     * @param array<string,mixed> $query the request's query parameters
     */
    public function look(array $query, ?\DateTimeImmutable $now = null): string
    {
        $asked = $query['theme'] ?? null;
        if (is_string($asked) && $this->canPreview() && in_array($asked, ['default', ...array_keys($this->events)], true)) {
            return $asked;
        }
        return $this->theme($now);
    }

    public function canPreview(): bool
    {
        return $this->preview;
    }

    /** The first day a closed seasonal template is offered again: "Halloween opens on 17 Oct". */
    public function opensOn(string $layout, ?\DateTimeImmutable $now = null): ?\DateTimeImmutable
    {
        if (!$this->isSeasonal($layout)) {
            return null;
        }
        $today = ($now ?? $this->now())->setTime(0, 0);
        $year = (int) $today->format('Y');
        $opens = $this->opening($layout, $this->day($layout, $year));
        return $opens > $today ? $opens : $this->opening($layout, $this->day($layout, $year + 1));
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

    /** The first day the template is offered for the given day of its event. */
    private function opening(string $layout, \DateTimeImmutable $day): \DateTimeImmutable
    {
        $lead = $this->events[$layout][2] ?? $this->leadDays;
        return $day->modify("-{$lead} days");
    }

    private function day(string $layout, int $year): \DateTimeImmutable
    {
        [$month, $day] = $this->events[$layout];
        return (new \DateTimeImmutable('now', new \DateTimeZone($this->timezone)))->setDate($year, $month, $day)->setTime(0, 0);
    }
}

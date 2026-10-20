<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Page;

/**
 * The plan catalogue (config/saas.php) and the rules for what a page may buy next.
 *
 * A plan is sold per period: `month` and `year` add their months to the time left, `once` keeps the page online for
 * good. What can be bought is an offer: a plan and one of its periods.
 */
final class Plans
{
    public const DEFAULT_PERIODS = ['month' => 1, 'year' => 12, 'once' => null];

    /**
     * @param array<string,array<string,mixed>> $plans in rank order, cheapest first
     * @param array<string,?int> $periods months each period buys; null = for good
     */
    public function __construct(
        private readonly array $plans,
        private readonly string $currency = 'PHP',
        private readonly array $periods = self::DEFAULT_PERIODS,
        private readonly bool|\Closure $subdomains = true,
    ) {
        if (array_key_exists(Order::PHOTO_PACK, $plans)) {
            throw new \InvalidArgumentException('"' . Order::PHOTO_PACK . '" names photo pack orders; give the plan another key.');
        }
    }

    /** @return array<string,array{key:string,name:string,prices:array<string,int>,photos:int,publish:bool,subdomain:bool,templates:string|list<string>,popular:bool,blurb:string}> */
    public function all(): array
    {
        $out = [];
        foreach ($this->plans as $key => $plan) {
            $out[$key] = ['key' => (string) $key] + $this->flagged($plan) + ['prices' => [], 'templates' => 'all', 'popular' => false];
        }
        return $out;
    }

    /**
     * Whether pages may have an address of their own (<name>.<domain>): the `subdomains` feature flag in config/saas.php.
     * Given to the constructor as a closure so the flag is read each time (tests and long-running workers see a change).
     */
    public function subdomainsEnabled(): bool
    {
        return (bool) ($this->subdomains instanceof \Closure ? ($this->subdomains)() : $this->subdomains);
    }

    /**
     * A plan as it is sold right now: what it includes (`subdomain`) only counts while the feature is on, and the blurb
     * that mentions the address (`blurb_subdomain`) is used only then.
     *
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    public function flagged(array $plan): array
    {
        if ($this->subdomainsEnabled()) {
            return isset($plan['blurb_subdomain']) ? ['blurb' => $plan['blurb_subdomain']] + $plan : $plan;
        }
        return ['subdomain' => false] + array_diff_key($plan, ['subdomain' => true]);
    }

    /** @return array<string,mixed>|null */
    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    /** Plans that put a page online (everything except the free preview). */
    public function paid(): array
    {
        return array_filter($this->all(), static fn (array $p): bool => (bool) $p['publish']);
    }

    public function rank(string $key): int
    {
        $index = array_search($key, array_keys($this->plans), true);
        return $index === false ? -1 : (int) $index;
    }

    /** The price of a plan for a period, in centavos; null when the plan is not sold that way. */
    public function price(string $plan, string $period): ?int
    {
        $price = $this->get($plan)['prices'][$period] ?? null;
        return $price === null ? null : (int) $price;
    }

    /** How many months a period buys; null = for good. @throws \DomainException for an unknown period */
    public function months(string $period): ?int
    {
        if (!array_key_exists($period, $this->periods)) {
            throw new \DomainException("Unknown billing period [{$period}].");
        }
        return $this->periods[$period] === null ? null : (int) $this->periods[$period];
    }

    /** A plan that, once bought, never expires (Forever). */
    public function isPermanent(string $plan): bool
    {
        $prices = $this->get($plan)['prices'] ?? [];
        foreach (array_keys($prices) as $period) {
            if (array_key_exists($period, $this->periods) && $this->periods[$period] === null) {
                return true;
            }
        }
        return false;
    }

    /**
     * What the owner can buy for this page right now: any paid plan above the current one, in any of its periods, and
     * the current plan again (a renewal, monthly or yearly) unless it never expires. Nothing is left to buy on a plan
     * that never expires. Each plan comes with its `offers`: period => price.
     *
     * @return array<string,array<string,mixed>>
     */
    public function purchasable(Page $page): array
    {
        $current = $page->plan;
        $currentPlan = $this->get($current);
        if ($currentPlan !== null && $currentPlan['publish'] && $this->isPermanent($current)) {
            return [];
        }
        $out = [];
        foreach ($this->paid() as $key => $plan) {
            if ($this->rank($key) > $this->rank($current) || ($key === $current && !$this->isPermanent($key))) {
                $out[$key] = $plan + ['offers' => array_map('intval', $plan['prices'])];
            }
        }
        return $out;
    }

    public function canBuy(Page $page, string $plan, string $period): bool
    {
        return isset($this->purchasable($page)[$plan]['offers'][$period]);
    }

    /** Whether a live page on this plan may use a template. */
    public function allowsTemplate(string $plan, string $template): bool
    {
        $templates = $this->get($plan)['templates'] ?? 'all';
        return $templates === 'all' || in_array($template, (array) $templates, true);
    }

    /** The paid plans that include a template, cheapest first: what to suggest when it is not in the page's plan. */
    public function plansWithTemplate(string $template): array
    {
        return array_keys(array_filter($this->paid(), fn (array $p): bool => $this->allowsTemplate($p['key'], $template)));
    }

    /** The template a page shows its visitors: its own, or the default when its plan does not include it. */
    public function templateFor(Page $page): string
    {
        $layout = Designs::layout($page->layout);
        if ($this->allowsTemplate($page->plan, $layout)) {
            return $layout;
        }
        foreach (array_keys(Designs::LAYOUTS) as $key) {
            if ($this->allowsTemplate($page->plan, $key)) {
                return $key;
            }
        }
        return Designs::DEFAULT_LAYOUT;
    }

    /** "Sweetheart and Forever" */
    public function names(array $keys): string
    {
        $names = array_map(fn (string $k): string => (string) ($this->get($k)['name'] ?? $k), array_values($keys));
        if (count($names) < 2) {
            return implode('', $names);
        }
        return implode(', ', array_slice($names, 0, -1)) . ' and ' . end($names);
    }

    public function currency(): string
    {
        return $this->currency;
    }

    /** ₱99, ₱1,250, ₱99.50 */
    public static function money(int $centavos, string $currency = 'PHP'): string
    {
        $symbol = $currency === 'PHP' ? '₱' : $currency . ' ';
        $whole = intdiv($centavos, 100);
        $cents = $centavos % 100;
        return $symbol . number_format($whole) . ($cents !== 0 ? '.' . str_pad((string) $cents, 2, '0', STR_PAD_LEFT) : '');
    }

    /** "a month", "a year", "once" */
    public static function per(string $period): string
    {
        return match ($period) {
            'month' => 'a month',
            'year' => 'a year',
            'once' => 'once',
            default => $period,
        };
    }

    /**
     * $date plus whole months, ending on the same day of the month or, when that day does not exist there, on the
     * last day (Jan 31 + 1 month = Feb 28 or 29, not Mar 3 as PHP's "+1 month" gives).
     */
    public static function addMonths(\DateTimeImmutable $date, int $months): \DateTimeImmutable
    {
        $day = (int) $date->format('j');
        $first = $date->setDate((int) $date->format('Y'), (int) $date->format('n'), 1)->modify("+{$months} months");
        $last = (int) $first->format('t');
        return $first->setDate((int) $first->format('Y'), (int) $first->format('n'), min($day, $last));
    }
}

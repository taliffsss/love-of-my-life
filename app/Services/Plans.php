<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Page;

/** The plan catalogue (config/saas.php) and the rules for what a page may buy next. */
final class Plans
{
    /** @param array<string,array<string,mixed>> $plans in rank order, cheapest first */
    public function __construct(private readonly array $plans, private readonly string $currency = 'PHP')
    {
    }

    /** @return array<string,array{key:string,name:string,price:int,days:?int,photos:int,publish:bool,subdomain:bool,blurb:string}> */
    public function all(): array
    {
        $out = [];
        foreach ($this->plans as $key => $plan) {
            $out[$key] = ['key' => (string) $key] + $plan;
        }
        return $out;
    }

    /** @return array{key:string,name:string,price:int,days:?int,photos:int,publish:bool,subdomain:bool,blurb:string}|null */
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

    /**
     * What the owner can buy for this page right now: any paid plan at least as high as the current one. Buying the same
     * time-limited plan again renews it; nothing can be bought once a page is on a plan that never expires.
     *
     * @return array<string,array<string,mixed>>
     */
    public function purchasable(Page $page): array
    {
        $current = $page->plan;
        $currentPlan = $this->get($current);
        if ($currentPlan !== null && $currentPlan['publish'] && $currentPlan['days'] === null) {
            return [];
        }
        return array_filter(
            $this->paid(),
            fn (array $p): bool => $this->rank($p['key']) > $this->rank($current)
                || ($p['key'] === $current && $p['days'] !== null),
        );
    }

    public function canBuy(Page $page, string $plan): bool
    {
        return isset($this->purchasable($page)[$plan]);
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
}

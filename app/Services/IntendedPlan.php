<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Naluz\Database\Connection;
use Naluz\Session\Store;

/**
 * The plan someone chose on the pricing section ("Start with Sweet"). Someone who is not signed in has it kept in the session
 * through sign-up or sign-in (also Google and Facebook), and it is saved on the account (`users.intended_plan`). While a page
 * is a draft, the dashboard then shows only the templates that plan includes (Sweet: 5 of 10), with a way to show them all.
 */
final class IntendedPlan
{
    private const SESSION = 'intended_plan';

    public function __construct(private readonly Plans $plans)
    {
    }

    /** A plan key that can be bought, or null. */
    public function valid(mixed $key): ?string
    {
        return is_string($key) && isset($this->plans->paid()[$key]) ? $key : null;
    }

    /** Keeps a choice made before signing in. */
    public function remember(Store $session, mixed $key): void
    {
        $plan = $this->valid($key);
        if ($plan !== null) {
            $session->put(self::SESSION, $plan);
        }
    }

    /** Saves the remembered choice (if any) on the account that has just signed up or in. */
    public function claim(Store $session, User $user, Connection $db): void
    {
        $plan = $this->valid($session->pull(self::SESSION));
        if ($plan !== null) {
            $this->save($user, $db, $plan);
        }
    }

    public function save(User $user, Connection $db, string $plan): void
    {
        $db->table('users')->where('id', '=', $user->id)->update(['intended_plan' => $plan]);
        $user->intended_plan = $plan;
    }

    /** The layout keys of the plan's templates, or null when the plan includes them all (or there is no plan). */
    public function layouts(?string $plan): ?array
    {
        $templates = $plan === null ? 'all' : ($this->plans->get($plan)['templates'] ?? 'all');
        return $templates === 'all' ? null : array_values((array) $templates);
    }

    public function name(?string $plan): string
    {
        return (string) ($plan !== null ? ($this->plans->get($plan)['name'] ?? $plan) : '');
    }
}

<?php

declare(strict_types=1);

use Naluz\Http\Middleware;

return [
    'name' => trim((string) env('APP_NAME', 'Love of My Life'), " \t\"'"),
    'env' => env('APP_ENV', 'production'),
    // Secure by default: debug output must be switched on explicitly.
    'debug' => (bool) env('APP_DEBUG', false),
    // Throw on accidental lazy loading (N+1). Defaults to on for APP_ENV=local|testing or when debugging.
    'prevent_lazy_loading' => env('PREVENT_LAZY_LOADING'),
    // Where `php naluz route:cache` writes the compiled route table (ignored while app.debug is true).
    'routes_cache' => null,
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'key' => env('APP_KEY', ''),
    // Keys APP_KEY replaced, comma separated: encrypted data written with them is still read (then php bin/reseal-payments.php).
    'previous_keys' => array_values(array_filter(array_map('trim', explode(',', (string) env('APP_PREVIOUS_KEYS', ''))))),
    'cache' => env('CACHE_DRIVER', 'file'),

    /** Composer packages whose auto-discovered providers should NOT be registered (or ['*'] for none). */
    'dont_discover' => [],

    /** Extra service providers: classes extending Naluz\Foundation\ServiceProvider. */
    'providers' => [
        App\Providers\AppServiceProvider::class,
    ],

    /** Runs on every request, outermost first. */
    'middleware' => [
        Middleware\SecurityHeaders::class,
        Middleware\Cors::class,
        Middleware\MethodOverride::class,
        // <name>.<saas domain> and custom domains are routed to their page before anything else (see the class)
        \App\Http\Middleware\ResolveSiteHost::class,
        \App\Http\Middleware\NoCookiesOnSeoFiles::class,
    ],

    'middleware_aliases' => [
        'session' => Middleware\StartSession::class,
        'csrf' => Middleware\VerifyCsrfToken::class,
        // the framework's Throttle never resets its counters (see App\Http\Middleware\RateLimit)
        'throttle' => \App\Http\Middleware\RateLimit::class,
        'active' => \App\Http\Middleware\EnsureActiveAccount::class,
        'admin' => \App\Http\Middleware\EnsureAdmin::class,
        'verified' => \App\Http\Middleware\EnsureEmailVerified::class,
        'noindex' => \App\Http\Middleware\NoIndex::class,
        'traffic' => \App\Http\Middleware\TrackTraffic::class,
        'auth' => Middleware\Authenticate::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'jwt' => Middleware\AuthenticateJwt::class,
    ],

    /** `web` wraps routes/web.php; `api` wraps routes/api.php (stateless: no cookies, no CSRF). */
    'middleware_groups' => [
        'web' => ['session', 'active', 'csrf'],
        'api' => ['throttle:60,1', 'noindex'],
    ],
];

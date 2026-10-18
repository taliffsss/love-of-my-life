<?php

declare(strict_types=1);

// The multi-page product: plans, the domain that dedicated subdomains live under, and how payments are taken.

$appHost = (string) (parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost');

return [
    // Brand shown in the dashboard, the landing page and e-mails.
    'brand' => env('APP_NAME', 'Love of My Life'),

    // Main domain of the product. Sweetheart and Forever pages can get <name>.<domain>; point a wildcard DNS record (*.domain) and
    // a wildcard certificate at this app and new subdomains work the moment they are claimed (see DEPLOY.md).
    'domain' => strtolower(trim((string) (env('SAAS_DOMAIN') ?: $appHost), '. ')),

    // Never handed out as a page subdomain.
    'reserved_subdomains' => [
        'about', 'account', 'accounts', 'admin', 'api', 'app', 'assets', 'auth', 'billing', 'blog', 'cdn', 'checkout',
        'dashboard', 'demo', 'dev', 'docs', 'email', 'ftp', 'help', 'imap', 'legal', 'login', 'logout', 'mail', 'media',
        'mx', 'ns1', 'ns2', 'pay', 'payment', 'payments', 'pop', 'pricing', 'privacy', 'register', 'root', 'secure',
        'signup', 'smtp', 'staging', 'static', 'status', 'support', 'terms', 'test', 'webmail', 'www',
    ],

    'currency' => 'PHP',

    // none   = no online checkout yet: the dashboard explains how to pay and an admin activates the plan
    //          (php bin/grant-plan.php <page> <plan>)
    // fake   = a test checkout that marks the order paid (refused when APP_ENV=production)
    'payments' => [
        'driver' => env('PAYMENTS_DRIVER', 'none'),
        // shown to customers while payments are manual
        'manual_instructions' => env('PAYMENTS_MANUAL_INSTRUCTIONS', 'Send the amount by GCash or Maya and reply with your order reference; your page goes live as soon as we confirm it.'),
    ],

    // How long a payment keeps a page online, in months; null = for good.
    'periods' => ['month' => 1, 'year' => 12, 'once' => null],

    // The plans, cheapest first (an upgrade is a move to the right). Prices are in centavos, per period: `month` and
    // `year` add their months to the time left (a year costs twelve months: there is no yearly discount), `once` never
    // expires. `photos`: what the plan adds to the account's photo allowance while its page is live (App\Services\PhotoAllowance);
    // an account with no live page gets the free preview's. `templates`: the templates a live page may use, 'all' or a list
    // of App\Services\Designs keys; the free preview shows every template so people can try them before choosing a plan. A
    // free page is a private draft: only its owner can preview it. See marketing/PRICING.md for the reasoning behind the
    // numbers. `photos` is not a plan key: orders use it for photo packs.
    'plans' => [
        'free' => ['name' => 'Free preview', 'prices' => [], 'photos' => 6, 'publish' => false, 'subdomain' => false,
            'templates' => 'all', 'popular' => false,
            'blurb' => 'Build the whole page and preview it. Only you can see it.'],
        'sweet' => ['name' => 'Sweet', 'prices' => ['month' => 20000, 'year' => 240000], 'photos' => 12, 'publish' => true,
            'subdomain' => false, 'templates' => ['classic', 'cinematic', 'polaroid', 'envelope', 'timeline'], 'popular' => false,
            'blurb' => 'Your page online, with 12 photos and 5 templates.'],
        'sweetheart' => ['name' => 'Sweetheart', 'prices' => ['month' => 35000, 'year' => 420000], 'photos' => 40, 'publish' => true,
            'subdomain' => true, 'templates' => 'all', 'popular' => true,
            'blurb' => 'All 10 templates, 40 photos and an address of its own.'],
        'forever' => ['name' => 'Forever', 'prices' => ['once' => 499900], 'photos' => 60, 'publish' => true,
            'subdomain' => true, 'templates' => 'all', 'popular' => false,
            'blurb' => 'Pay once and it stays online for good: 60 photos, every template, its own address.'],
    ],

    // More photos for an account that has used its allowance: bought once, kept for good, shared by all its pages.
    // An order buys 1 to `max_packs` packs.
    'photo_pack' => ['photos' => 12, 'price' => 10000, 'max_packs' => 10],

    // The largest photo accepted, before and after the browser shrinks it (10 MB, as a phone or computer counts them).
    // PHP and nginx accept bodies up to 16 MB (docker/php/php.ini, docker/nginx/default.conf), so larger files reach
    // the app and get a clear answer instead of a bare error.
    'max_upload_bytes' => 10 * 1024 * 1024,
];
